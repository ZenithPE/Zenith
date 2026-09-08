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

namespace pocketmine\tools\ping_server;

use altay\network\nethernet\discovery\DiscoveryCodec;
use altay\network\nethernet\discovery\DiscoveryRequestPacket;
use altay\network\nethernet\discovery\DiscoveryResponsePacket;
use altay\network\nethernet\NetherNetTransport;
use altay\network\nethernet\ServerData;
use altay\network\raknet\protocol\MessageIdentifiers;
use altay\network\raknet\protocol\PacketSerializer;
use altay\network\raknet\protocol\UnconnectedPing;
use altay\network\raknet\protocol\UnconnectedPong;
use pocketmine\utils\BinaryDataException;
use pocketmine\utils\BinaryStream;
use pocketmine\utils\Utils;
use function bin2hex;
use function count;
use function fgets;
use function gethostbynamel;
use function hrtime;
use function intdiv;
use function mt_rand;
use function ord;
use function sleep;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_getsockname;
use function socket_last_error;
use function socket_recvfrom;
use function socket_select;
use function socket_sendto;
use function socket_strerror;
use function strlen;
use function strtolower;
use function time;
use function trim;
use const AF_INET;
use const MSG_DONTROUTE;
use const PHP_BINARY;
use const PHP_INT_MAX;
use const SOCK_DGRAM;
use const SOL_UDP;
use const STDIN;

require_once 'vendor/autoload.php';

const TRANSPORT_RAKNET = "raknet";
const TRANSPORT_NETHERNET = "nethernet";

const RAKNET_DEFAULT_PORT = 19132;

function hrtime_ms() : int{
	return intdiv(hrtime(true), 1_000_000);
}

function read_stdin(string $prompt) : string{
	echo $prompt . ": ";
	$input = fgets(STDIN);
	if($input === false){
		exit(1); //this probably means the user pressed ctrl+c
	}
	return trim($input);
}

/**
 * @return string|null the raw datagram, or null if nothing usable arrived before the timeout
 */
function await_response(\Socket $socket, string $serverIp, int $serverPort, int $timeoutSeconds) : ?string{
	$r = [$socket];
	$w = $e = null;
	if(socket_select($r, $w, $e, $timeoutSeconds) !== 1){
		\GlobalLogger::get()->info("No ping response after $timeoutSeconds seconds");
		return null;
	}
	$response = @socket_recvfrom($socket, $recvBuffer, 65535, 0, $recvAddr, $recvPort);
	if($response === false){
		\GlobalLogger::get()->error("Error reading from socket: " . socket_strerror(socket_last_error($socket)));
		return null;
	}
	if($recvAddr !== $serverIp || $recvPort !== $serverPort){
		\GlobalLogger::get()->debug("Garbage packet from $recvAddr $recvPort: " . bin2hex($recvBuffer));
		return null;
	}
	return $recvBuffer;
}

function send_datagram(\Socket $socket, string $payload, string $serverIp, int $serverPort) : bool{
	if(@socket_sendto($socket, $payload, strlen($payload), MSG_DONTROUTE, $serverIp, $serverPort) === false){
		\GlobalLogger::get()->error("Failed to send ping: " . socket_strerror(socket_last_error($socket)));
		return false;
	}
	\GlobalLogger::get()->info("Ping sent to $serverIp on port $serverPort, waiting for response (press CTRL+C to abort)");
	return true;
}

function ping_raknet(\Socket $socket, string $serverIp, int $serverPort, int $timeoutSeconds, int $clientId) : bool{
	$ping = new UnconnectedPing();
	$ping->sendPingTime = hrtime_ms();
	$ping->clientId = $clientId;
	$serializer = new PacketSerializer();
	$ping->encode($serializer);
	if(!send_datagram($socket, $serializer->getBuffer(), $serverIp, $serverPort)){
		return false;
	}

	$recvBuffer = await_response($socket, $serverIp, $serverPort, $timeoutSeconds);
	if($recvBuffer === null){
		return false;
	}
	if($recvBuffer === "" || ord($recvBuffer[0]) !== MessageIdentifiers::ID_UNCONNECTED_PONG){
		\GlobalLogger::get()->debug("Unexpected packet: " . bin2hex($recvBuffer));
		return false;
	}

	$pong = new UnconnectedPong();
	$pong->decode(new PacketSerializer($recvBuffer));
	\GlobalLogger::get()->info("--- Response received ---");
	\GlobalLogger::get()->info("Payload: $pong->serverName");
	\GlobalLogger::get()->info("Response time: " . (hrtime_ms() - $pong->sendPingTime) . " ms");
	return true;
}

function ping_nethernet(\Socket $socket, string $serverIp, int $serverPort, int $timeoutSeconds, int $clientId) : bool{
	$sendTime = hrtime_ms();
	if(!send_datagram($socket, DiscoveryCodec::marshal(new DiscoveryRequestPacket(), $clientId), $serverIp, $serverPort)){
		return false;
	}

	$recvBuffer = await_response($socket, $serverIp, $serverPort, $timeoutSeconds);
	if($recvBuffer === null){
		return false;
	}
	$unmarshalled = DiscoveryCodec::unmarshal($recvBuffer);
	if($unmarshalled === null){
		\GlobalLogger::get()->debug("Undecodable discovery packet: " . bin2hex($recvBuffer));
		return false;
	}
	[$packet, $senderId] = $unmarshalled;
	if(!$packet instanceof DiscoveryResponsePacket){
		\GlobalLogger::get()->debug("Unexpected discovery packet: " . $packet->getId());
		return false;
	}

	\GlobalLogger::get()->info("--- Response received ---");
	\GlobalLogger::get()->info("Network ID: $senderId");
	$serverData = decode_server_data($packet->applicationData);
	if($serverData === null){
		\GlobalLogger::get()->warning("Server sent malformed application data: " . bin2hex($packet->applicationData));
	}else{
		\GlobalLogger::get()->info("Payload: " . $serverData["serverName"]);
		\GlobalLogger::get()->info("Level: " . $serverData["levelName"]);
		\GlobalLogger::get()->info("Players: " . $serverData["playerCount"] . "/" . $serverData["maxPlayerCount"]);
	}
	\GlobalLogger::get()->info("Response time: " . (hrtime_ms() - $sendTime) . " ms");
	return true;
}

/**
 * Mirrors ServerData::encode(), which has no decoder since only clients need to read it.
 *
 * @return array{serverName: string, levelName: string, playerCount: int, maxPlayerCount: int}|null
 */
function decode_server_data(string $data) : ?array{
	try{
		$serverData = ServerData::decode($data);
	}catch(BinaryDataException){
		return null;
	}
	return [
		"serverName" => $serverData->serverName,
		"levelName" => $serverData->levelName,
		"playerCount" => $serverData->playerCount,
		"maxPlayerCount" => $serverData->maxPlayerCount
	];
}

$argv ??= [];
if(count($argv) > 4){
	echo "Usage: " . PHP_BINARY . " " . __FILE__ . " [server IP] [server port] [transport]\n";
	echo "Transport may be \"nethernet\" (default) or \"raknet\".\n";
	exit(1);
}

if(count($argv) > 1){
	$hostName = $argv[1];
}else{
	do{
		$hostName = read_stdin("Server address");
	}while($hostName === "");
}

$serverIps = gethostbynamel($hostName);
if($serverIps === false){
	\GlobalLogger::get()->critical("Unable to resolve hostname $hostName to an IP address");
	exit(1);
}
if(count($serverIps) > 1){
	\GlobalLogger::get()->warning("Multiple IP addresses found for hostname $hostName, using the first one: " . $serverIps[0]);
}
$server = $serverIps[0];
\GlobalLogger::get()->info("Resolved hostname to $server");

if(count($argv) > 3){
	$transport = strtolower($argv[3]);
}elseif(count($argv) > 1){
	$transport = TRANSPORT_NETHERNET;
}else{
	$transportRaw = strtolower(read_stdin("Transport, nethernet or raknet (empty for nethernet)"));
	$transport = $transportRaw === "" ? TRANSPORT_NETHERNET : $transportRaw;
}
if($transport !== TRANSPORT_NETHERNET && $transport !== TRANSPORT_RAKNET){
	\GlobalLogger::get()->critical("Unknown transport \"$transport\", expected \"nethernet\" or \"raknet\"");
	exit(1);
}
$defaultPort = $transport === TRANSPORT_NETHERNET ? NetherNetTransport::DISCOVERY_PORT : RAKNET_DEFAULT_PORT;

if(count($argv) > 2){
	$port = (int) $argv[2];
}elseif(count($argv) > 1){
	$port = $defaultPort;
}else{
	$portRaw = read_stdin("Server port (empty for $defaultPort)");
	$port = $portRaw === "" ? $defaultPort : (int) $portRaw;
}

$sock = Utils::assumeNotFalse(socket_create(AF_INET, SOCK_DGRAM, SOL_UDP));

socket_bind($sock, "0.0.0.0");
socket_getsockname($sock, $bindAddr, $bindPort);
\GlobalLogger::get()->info("Bound to $bindAddr on port $bindPort");

$clientId = mt_rand(0, PHP_INT_MAX);
$start = time();
while(time() < $start + 60_000){
	$success = $transport === TRANSPORT_NETHERNET ?
		ping_nethernet($sock, $server, $port, 5, $clientId) :
		ping_raknet($sock, $server, $port, 5, $clientId);
	if($success){
		break;
	}
	sleep(1);
}

socket_close($sock);
