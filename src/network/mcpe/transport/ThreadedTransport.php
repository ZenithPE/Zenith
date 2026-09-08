<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\transport;

use altay\network\ipc\MainToTransportThreadMessageSender;
use altay\network\ipc\TransportToMainThreadEventHandler;
use altay\network\ipc\TransportToMainThreadMessageReceiver;
use altay\network\transport\AddressBlockingTransport;
use altay\network\transport\NameableTransport;
use altay\network\transport\RawPacketTransport;
use altay\network\transport\TransportException;
use altay\network\transport\TransportListener;
use altay\network\transport\TransportSession;
use altay\network\transport\TunableTransport;
use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandler;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\ThreadCrashException;

/**
 * Uniform main-thread proxy for a transport running in its own thread. It forwards every server
 * control command; the transport thread authoritatively ignores commands its transport does not
 * support, so this safely advertises all capability interfaces.
 */
final class ThreadedTransport implements NameableTransport, RawPacketTransport, AddressBlockingTransport, TunableTransport, TransportToMainThreadEventHandler{

	private ?TransportThread $thread = null;
	private ?TransportListener $listener = null;
	private ?MainToTransportThreadMessageSender $commandSender = null;
	private ?TransportToMainThreadMessageReceiver $eventReceiver = null;

	/** @var RemoteTransportSession[] */
	private array $sessions = [];

	private int $sleeperNotifierId;
	private SleeperHandlerEntry $sleeperEntry;

	public function __construct(
		private ThreadSafeLogger $logger,
		private TransportFactory $factory,
		private SleeperHandler $sleeper
	){
		$this->sleeperEntry = $this->sleeper->addNotifier(function() : void{
			$this->processEvents();
		});
		$this->sleeperNotifierId = $this->sleeperEntry->getNotifierId();
	}

	public function getName() : string{
		return $this->factory->getName();
	}

	public function getServerId() : ?int{
		return $this->factory instanceof RakNetTransportFactory ? $this->factory->getServerId() : null;
	}

	public function start(TransportListener $listener) : void{
		if($this->thread !== null){
			throw new TransportException("Transport thread is already running");
		}
		$this->listener = $listener;

		/** @phpstan-var ThreadSafeArray<int, string> $mainToThreadBuffer */
		$mainToThreadBuffer = new ThreadSafeArray();
		/** @phpstan-var ThreadSafeArray<int, string> $threadToMainBuffer */
		$threadToMainBuffer = new ThreadSafeArray();

		$thread = new TransportThread(
			$this->logger,
			$mainToThreadBuffer,
			$threadToMainBuffer,
			$this->factory,
			$this->sleeperEntry
		);
		try{
			$thread->startAndWait();
		}catch(ThreadCrashException $e){
			throw new TransportException("Transport thread failed to start: " . $e->getMessage(), 0, $e);
		}

		$this->thread = $thread;
		$this->commandSender = new MainToTransportThreadMessageSender(new PthreadsChannelWriter($mainToThreadBuffer));
		$this->eventReceiver = new TransportToMainThreadMessageReceiver(new PthreadsChannelReader($threadToMainBuffer));
	}

	public function tick() : void{
		if($this->thread !== null && !$this->thread->isRunning()){
			$e = $this->thread->getCrashInfo();
			if($e !== null){
				throw new ThreadCrashException("Transport thread crashed", $e);
			}
			throw new \RuntimeException("Transport thread crashed without crash information");
		}
		$this->processEvents();
	}

	private function processEvents() : void{
		if($this->eventReceiver !== null){
			while($this->eventReceiver->handle($this));
		}
	}

	public function isSelfPacing() : bool{
		//event draining on the main thread is non-blocking; the inner transport paces itself in its own thread
		return false;
	}

	public function isRunning() : bool{
		return $this->thread !== null;
	}

	public function shutdown() : void{
		if($this->thread !== null){
			$this->commandSender?->shutdown();
			$this->thread->quit();
			$this->thread = null;
		}
		$this->sleeper->removeNotifier($this->sleeperNotifierId);
		foreach($this->sessions as $session){
			$session->markDisconnected();
		}
		$this->sessions = [];
		$this->commandSender = null;
		$this->eventReceiver = null;
		$this->listener = null;
	}

	public function getSession(int $sessionId) : ?TransportSession{
		return $this->sessions[$sessionId] ?? null;
	}

	public function handleSessionOpen(int $sessionId, string $address, int $port, ?string $authenticatedPublicKey) : void{
		if($this->commandSender === null){
			return;
		}
		$session = new RemoteTransportSession($this->commandSender, $sessionId, $address, $port, $authenticatedPublicKey);
		$this->sessions[$sessionId] = $session;
		$this->listener?->onSessionOpen($this, $session);
	}

	public function handleSessionClose(int $sessionId, string $reason) : void{
		$session = $this->sessions[$sessionId] ?? null;
		if($session !== null){
			unset($this->sessions[$sessionId]);
			$session->markDisconnected();
			$this->listener?->onSessionClose($this, $session, $reason);
		}
	}

	public function handlePacketReceive(int $sessionId, string $payload) : void{
		$session = $this->sessions[$sessionId] ?? null;
		if($session !== null){
			$this->listener?->onPacketReceive($this, $session, $payload);
		}
	}

	public function handlePacketAck(int $sessionId, int $receiptId) : void{
		$session = $this->sessions[$sessionId] ?? null;
		if($session !== null){
			$this->listener?->onPacketAck($this, $session, $receiptId);
		}
	}

	public function handlePingUpdate(int $sessionId, int $pingMS) : void{
		$session = $this->sessions[$sessionId] ?? null;
		if($session !== null){
			$session->updatePing($pingMS);
			$this->listener?->onPingUpdate($this, $session, $pingMS);
		}
	}

	public function handleRawPacketReceive(string $address, int $port, string $payload) : void{
		$this->listener?->onRawPacketReceive($this, $address, $port, $payload);
	}

	public function handleBandwidthUpdate(int $bytesSentDiff, int $bytesReceivedDiff) : void{
		$this->listener?->onBandwidthUpdate($this, $bytesSentDiff, $bytesReceivedDiff);
	}

	public function setName(string $name) : void{
		$this->commandSender?->setName($name);
	}

	public function blockAddress(string $address, int $timeout = 300) : void{
		$this->commandSender?->blockAddress($address, $timeout);
	}

	public function unblockAddress(string $address) : void{
		$this->commandSender?->unblockAddress($address);
	}

	public function setPortCheck(bool $value) : void{
		$this->commandSender?->setPortCheck($value);
	}

	public function setPacketsPerTickLimit(int $limit) : void{
		$this->commandSender?->setPacketsPerTickLimit($limit);
	}

	public function addRawPacketFilter(string $regex) : void{
		$this->commandSender?->addRawPacketFilter($regex);
	}

	public function sendRaw(string $address, int $port, string $payload) : void{
		$this->commandSender?->sendRaw($address, $port, $payload);
	}
}
