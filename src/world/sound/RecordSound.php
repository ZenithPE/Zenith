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

use pocketmine\block\utils\RecordType;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\RecordStartedPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

class RecordSound implements Sound{

	private static int $soundHandleCount = 1;

	private int $serverSoundHandle;

	public function __construct(private RecordType $recordType){
		$this->serverSoundHandle = self::$soundHandleCount++;
	}

	public function getServerSoundHandle() : int{
		return $this->serverSoundHandle;
	}

	public function encode(Vector3 $pos) : array{
		return [
			PlaySoundPacket::create($this->recordType->getSoundId(), $pos->x + 0.5, $pos->y + 0.5, $pos->z + 0.5, 1, 1, 0, true, $this->serverSoundHandle, null),
			RecordStartedPacket::create(BlockPosition::fromVector3($pos), $this->serverSoundHandle) // new one, love its being unique
		];
	}
}
