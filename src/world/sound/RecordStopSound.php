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

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ClientboundUpdateSoundDataPacket;
use pocketmine\network\mcpe\protocol\types\sound\StopSoundData;

class RecordStopSound implements Sound{

	public function __construct(private int $serverSoundHandle){}

	public function encode(Vector3 $pos) : array{
		$stop = new StopSoundData();

		return [ClientboundUpdateSoundDataPacket::create($this->serverSoundHandle, $stop, $stop, $stop, $stop, $stop, $stop, $stop)];
	}
}
