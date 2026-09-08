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

use altay\network\transport\AddressBlockingTransport;
use altay\network\transport\NameableTransport;
use altay\network\transport\RawPacketTransport;
use altay\network\transport\Transport;
use altay\network\transport\TransportException;
use altay\network\transport\TransportListener;
use altay\network\transport\TransportSession;
use altay\network\transport\TunableTransport;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\network\AdvancedNetworkInterface;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\Network;
use pocketmine\network\NetworkInterfaceStartException;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\GameMode;
use pocketmine\Server;
use pocketmine\utils\Utils;
use function addcslashes;
use function base64_encode;
use function implode;
use function rtrim;
use function substr;

class TransportNetworkInterface implements AdvancedNetworkInterface, TransportListener{

	private const MCPE_PACKET_ID = "\xfe";

	private Network $network;
	private bool $rakNetFraming;

	/** @var NetworkSession[] */
	private array $sessions = [];
	/** @var TransportSession[] */
	private array $transportSessions = [];

	public function __construct(
		private Server $server,
		private Transport $transport,
		private PacketBroadcaster $packetBroadcaster,
		private EntityEventBroadcaster $entityEventBroadcaster,
		private TypeConverter $typeConverter
	){
		$this->rakNetFraming = $transport->getName() === "raknet";
	}

	public function start() : void{
		try{
			$this->transport->start($this);
		}catch(TransportException $e){
			throw new NetworkInterfaceStartException($e->getMessage(), 0, $e);
		}
	}

	public function setNetwork(Network $network) : void{
		$this->network = $network;
	}

	public function tick() : void{
		$this->transport->tick();
	}

	public function shutdown() : void{
		$this->transport->shutdown();
	}

	public function onSessionOpen(Transport $transport, TransportSession $session) : void{
		$networkSession = new NetworkSession(
			$this->server,
			$this->network->getSessionManager(),
			PacketPool::getInstance(),
			new TransportPacketSender($session, $this, $this->rakNetFraming),
			$this->packetBroadcaster,
			$this->entityEventBroadcaster,
			ZlibCompressor::getInstance(),
			$this->typeConverter,
			$session->getAddress(),
			$session->getPort(),
			//NetherNet connections are already encrypted at the DTLS layer, vanilla clients
			//do not use Bedrock-layer encryption on top of it
			$this->rakNetFraming,
			$session->getAuthenticatedPublicKey()
		);
		$this->sessions[$session->getId()] = $networkSession;
		$this->transportSessions[$session->getId()] = $session;
	}

	public function onSessionClose(Transport $transport, TransportSession $session, string $reason) : void{
		$sessionId = $session->getId();
		if(isset($this->sessions[$sessionId])){
			$networkSession = $this->sessions[$sessionId];
			unset($this->sessions[$sessionId], $this->transportSessions[$sessionId]);
			$networkSession->onClientDisconnect(match($reason){
				"client disconnect" => KnownTranslationFactory::pocketmine_disconnect_clientDisconnect(),
				"timeout" => KnownTranslationFactory::pocketmine_disconnect_error_timeout(),
				"client reconnect" => KnownTranslationFactory::pocketmine_disconnect_clientReconnect(),
				default => "Disconnected: $reason"
			});
		}
	}

	public function close(int $sessionId) : void{
		if(isset($this->sessions[$sessionId])){
			$transportSession = $this->transportSessions[$sessionId];
			unset($this->sessions[$sessionId], $this->transportSessions[$sessionId]);
			$transportSession->disconnect();
		}
	}

	public function onPacketReceive(Transport $transport, TransportSession $session, string $payload) : void{
		$sessionId = $session->getId();
		if(isset($this->sessions[$sessionId])){
			if($this->rakNetFraming){
				if($payload === "" || $payload[0] !== self::MCPE_PACKET_ID){
					$this->sessions[$sessionId]->getLogger()->debug("Non-FE packet received: " . base64_encode($payload));
					return;
				}
				$buf = substr($payload, 1);
			}else{
				if($payload === ""){
					return;
				}
				$buf = $payload;
			}
			$networkSession = $this->sessions[$sessionId];
			$address = $networkSession->getIp();
			$name = $networkSession->getDisplayName();
			try{
				$networkSession->handleEncoded($buf);
			}catch(PacketHandlingException $e){
				$logger = $networkSession->getLogger();

				$networkSession->disconnectWithError(
					reason: "Bad packet: " . $e->getMessage(),
					disconnectScreenMessage: KnownTranslationFactory::pocketmine_disconnect_error_badPacket()
				);
				$logger->debug(implode("\n", Utils::printableExceptionInfo($e)));

				$this->blockAddress($address, 5);
			}catch(\Throwable $e){
				$this->server->getLogger()->emergency("Crash occurred while handling a packet from session: $name");
				throw $e;
			}
		}
	}

	public function onPacketAck(Transport $transport, TransportSession $session, int $receiptId) : void{
		if(isset($this->sessions[$session->getId()])){
			$this->sessions[$session->getId()]->handleAckReceipt($receiptId);
		}
	}

	public function onPingUpdate(Transport $transport, TransportSession $session, int $pingMS) : void{
		if(isset($this->sessions[$session->getId()])){
			$this->sessions[$session->getId()]->updatePing($pingMS);
		}
	}

	public function onRawPacketReceive(Transport $transport, string $address, int $port, string $payload) : void{
		$this->network->processRawPacket($this, $address, $port, $payload);
	}

	public function onBandwidthUpdate(Transport $transport, int $bytesSentDiff, int $bytesReceivedDiff) : void{
		$this->network->getBandwidthTracker()->add($bytesSentDiff, $bytesReceivedDiff);
	}

	public function blockAddress(string $address, int $timeout = 300) : void{
		if($this->transport instanceof AddressBlockingTransport){
			$this->transport->blockAddress($address, $timeout);
		}
	}

	public function unblockAddress(string $address) : void{
		if($this->transport instanceof AddressBlockingTransport){
			$this->transport->unblockAddress($address);
		}
	}

	public function sendRawPacket(string $address, int $port, string $payload) : void{
		if($this->transport instanceof RawPacketTransport){
			$this->transport->sendRaw($address, $port, $payload);
		}
	}

	public function addRawPacketFilter(string $regex) : void{
		if($this->transport instanceof RawPacketTransport){
			$this->transport->addRawPacketFilter($regex);
		}
	}

	public function setName(string $name) : void{
		if(!($this->transport instanceof NameableTransport)){
			return;
		}
		$info = $this->server->getQueryInformation();

		$this->transport->setName(implode(";",
			[
				"MCPE",
				rtrim(addcslashes($name, ";"), '\\'),
				ProtocolInfo::CURRENT_PROTOCOL,
				ProtocolInfo::MINECRAFT_VERSION_NETWORK,
				$info->getPlayerCount(),
				$info->getMaxPlayerCount(),
				$this->transport->getServerId() ?? 0,
				$this->server->getName(),
				match($this->server->getGamemode()){
					GameMode::SURVIVAL => "Survival",
					GameMode::ADVENTURE => "Adventure",
					default => "Creative"
				}
			]) . ";"
		);
	}

	public function setPortCheck(bool $name) : void{
		if($this->transport instanceof TunableTransport){
			$this->transport->setPortCheck($name);
		}
	}

	public function setPacketLimit(int $limit) : void{
		if($this->transport instanceof TunableTransport){
			$this->transport->setPacketsPerTickLimit($limit);
		}
	}
}
