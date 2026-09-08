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

use altay\network\transport\TransportSession;
use pocketmine\network\mcpe\PacketSender;

class TransportPacketSender implements PacketSender{
	private const MCPE_PACKET_ID = "\xfe";

	private bool $closed = false;

	public function __construct(
		private TransportSession $session,
		private TransportNetworkInterface $handler,
		private bool $rakNetFraming
	){}

	public function send(string $payload, bool $immediate, ?int $receiptId) : void{
		if(!$this->closed){
			$this->session->sendPacket(($this->rakNetFraming ? self::MCPE_PACKET_ID : "") . $payload, $immediate, $receiptId);
		}
	}

	public function close(string $reason = "unknown reason") : void{
		if(!$this->closed){
			$this->closed = true;
			$this->handler->close($this->session->getId());
		}
	}
}
