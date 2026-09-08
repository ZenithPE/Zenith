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

namespace pocketmine\block;

use pocketmine\entity\Entity;
use pocketmine\player\Player;

class StrawBed extends BedBase{

	public function setsRespawnPoint() : bool{
		return false;
	}

	public function onSleepEnd(Player $player) : void{
		//a straw bed is single use so it falls apart once someone has been in it
		$world = $this->position->getWorld();
		if(($other = $this->getOtherHalf()) !== null){
			$world->setBlock($other->position, VanillaBlocks::AIR());
		}
		$world->setBlock($this->position, VanillaBlocks::AIR());
	}

	public function onEntityLand(Entity $entity) : ?float{
		//straw isn't bouncy like wool is
		return null;
	}
}
