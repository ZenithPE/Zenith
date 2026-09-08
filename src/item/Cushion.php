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

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Location;
use pocketmine\entity\object\Cushion as EntityCushion;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Cushion extends Item{
	private DyeColor $color = DyeColor::WHITE;

	public function getColor() : DyeColor{ return $this->color; }

	/** @return $this */
	public function setColor(DyeColor $color) : self{
		$this->color = $color;
		return $this;
	}

	protected function describeState(RuntimeDataDescriber $w) : void{
		$w->enum($this->color);
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if(!$blockReplace->canBeReplaced() || $blockReplace instanceof Liquid){
			return ItemUseResult::NONE;
		}

		//a cushion rests on whatever is under it, so it needs a surface to sit on
		if($blockReplace->getSide(Facing::DOWN)->getSupportType(Facing::UP) === SupportType::NONE){
			return ItemUseResult::NONE;
		}

		$pos = $blockReplace->getPosition();
		$world = $pos->getWorld();

		//a cushion is barely taller than the floor, so only its own cell has to be clear of other ones
		$boundingBox = AxisAlignedBB::one()->offset($pos->getX(), $pos->getY(), $pos->getZ());
		foreach($world->getNearbyEntities($boundingBox) as $entity){
			if($entity instanceof EntityCushion){
				return ItemUseResult::NONE;
			}
		}

		$location = Location::fromObject(
			$pos->add(0.5, 0, 0.5),
			$world,
			$player->getLocation()->getYaw(),
			0.0
		);

		$cushion = new EntityCushion($location);
		$cushion->setColor($this->color);
		$cushion->spawnToAll();

		$this->pop();
		return ItemUseResult::SUCCESS;
	}

	public function getMaxStackSize() : int{
		return 64;
	}

	public function getFuelTime() : int{
		return 200;
	}
}
