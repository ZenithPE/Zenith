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

use altay\network\raknet\RakNetTransport;
use altay\network\transport\Transport;

final class RakNetTransportFactory implements TransportFactory{

	public function __construct(
		private string $ip,
		private int $port,
		private bool $ipV6,
		private int $maxMtuSize,
		private int $serverId
	){}

	public function getName() : string{
		return "raknet";
	}

	public function getServerId() : int{
		return $this->serverId;
	}

	public function make(\Logger $logger) : Transport{
		return new RakNetTransport(
			$logger,
			$this->ip,
			$this->port,
			$this->ipV6,
			$this->maxMtuSize,
			RakNetTransport::BEDROCK_RAKNET_PROTOCOL_VERSION,
			$this->serverId
		);
	}
}
