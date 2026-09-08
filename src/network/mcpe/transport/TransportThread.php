<?php

/*
 *
 *      _    _ _
 *     / \  | | |_ __ _ _   _
 *    / _ \ | | __/ _` | | | |
 *   / ___ \| | || (_| | |_| |
 *  /_/   \_\_|\__\__,_|\__, |
 *                       |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Original work by the PocketMine Team.
 * https://www.pocketmine.net/
 *
 * @author Altay Team
 * @link https://github.com/altayofficial
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\transport;

use altay\network\ipc\MainToTransportThreadMessageReceiver;
use altay\network\ipc\TransportToMainThreadMessageSender;
use altay\network\transport\TransportException;
use pmmp\thread\Thread as NativeThread;
use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\thread\Thread;
use pocketmine\thread\ThreadCrashException;
use function gc_disable;
use function hrtime;
use function ini_set;
use function intdiv;
use function usleep;

class TransportThread extends Thread{

	private const TICK_INTERVAL_MICROS = 5000;

	protected bool $ready = false;
	protected string $mainPath;
	/** @phpstan-var NonThreadSafeValue<TransportFactory> */
	protected NonThreadSafeValue $factory;

	/**
	 * @phpstan-param ThreadSafeArray<int, string> $mainToThreadBuffer
	 * @phpstan-param ThreadSafeArray<int, string> $threadToMainBuffer
	 */
	public function __construct(
		protected ThreadSafeLogger $logger,
		protected ThreadSafeArray $mainToThreadBuffer,
		protected ThreadSafeArray $threadToMainBuffer,
		TransportFactory $factory,
		protected SleeperHandlerEntry $sleeperEntry
	){
		$this->mainPath = \pocketmine\PATH;
		$this->factory = new NonThreadSafeValue($factory);
	}

	public function startAndWait(int $options = NativeThread::INHERIT_NONE) : void{
		$this->start($options);
		$this->synchronized(function() : void{
			while(!$this->ready && !$this->isTerminated()){
				$this->wait();
			}
		});
		$crashInfo = $this->getCrashInfo();
		if($crashInfo !== null){
			if($crashInfo->getType() === TransportException::class){
				throw new TransportException($crashInfo->getMessage());
			}
			throw new ThreadCrashException("Transport thread failed to start", $crashInfo);
		}
	}

	protected function onRun() : void{
		gc_disable();

		ini_set("display_errors", '1');
		ini_set("display_startup_errors", '1');
		\GlobalLogger::set($this->logger);

		$transport = $this->factory->deserialize()->make($this->logger);
		$transport->start(new TransportToMainThreadMessageSender(
			new SnoozeAwarePthreadsChannelWriter($this->threadToMainBuffer, $this->sleeperEntry->createNotifier())
		));

		$commandReceiver = new MainToTransportThreadMessageReceiver(
			new PthreadsChannelReader($this->mainToThreadBuffer)
		);

		$this->synchronized(function() : void{
			$this->ready = true;
			$this->notify();
		});

		//transports that block internally to pace themselves must not be slept on top of, or they lag
		$selfPacing = $transport->isSelfPacing();

		while(!$this->isKilled && !$commandReceiver->isShutdownRequested()){
			$start = hrtime(true);

			while($commandReceiver->handle($transport));
			if($commandReceiver->isShutdownRequested()){
				break;
			}
			$transport->tick();

			if(!$selfPacing){
				$elapsedMicros = intdiv(hrtime(true) - $start, 1000);
				if($elapsedMicros < self::TICK_INTERVAL_MICROS){
					usleep(self::TICK_INTERVAL_MICROS - $elapsedMicros);
				}
			}
		}

		while($commandReceiver->handle($transport));
		$transport->shutdown();
	}

	public function getThreadName() : string{
		return "Transport";
	}
}
