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

namespace pocketmine\block\tile;

use pocketmine\item\Item;
use pocketmine\item\Record;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\sound\RecordStopSound;

class Jukebox extends Spawnable{
	private const TAG_RECORD = "RecordItem"; //Item CompoundTag

	private ?Record $record = null;

	private ?int $soundHandle = null;

	public function getRecord() : ?Record{
		return $this->record;
	}

	public function setRecord(?Record $record) : void{
		$this->record = $record;
	}

	/**
	 * Returns the handle of the record sound currently playing, or null if nothing is playing. This is only known at
	 * runtime, since the client forgets about the sound as soon as it leaves the area.
	 */
	public function getSoundHandle() : ?int{
		return $this->soundHandle;
	}

	public function setSoundHandle(?int $soundHandle) : void{
		$this->soundHandle = $soundHandle;
	}

	public function readSaveData(CompoundTag $nbt) : void{
		if(($tag = $nbt->getCompoundTag(self::TAG_RECORD)) !== null){
			$record = Item::safeNbtDeserialize($tag, "Jukebox ($this->position) record");
			if($record instanceof Record){
				$this->record = $record;
			}
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		if($this->record !== null){
			$nbt->setTag(self::TAG_RECORD, $this->record->nbtSerialize());
		}
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		//this is needed for the note particles to show on the client side
		if($this->record !== null){
			$nbt->setTag(self::TAG_RECORD, TypeConverter::getInstance()->getItemTranslator()->toNetworkNbt($this->record));
		}
	}

	protected function onBlockDestroyedHook() : void{
		if($this->soundHandle !== null){
			$this->position->getWorld()->addSound($this->position, new RecordStopSound($this->soundHandle));
			$this->soundHandle = null;
		}
	}
}
