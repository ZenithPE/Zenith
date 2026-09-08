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

use pocketmine\block\utils\Ageable;
use pocketmine\block\utils\AgeableTrait;
use pocketmine\block\utils\BlockEventHelper;
use pocketmine\block\utils\CoveredWithWaterTrait;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function mt_rand;

class Kelp extends Flowable implements Ageable{
	use AgeableTrait;
	use CoveredWithWaterTrait;

	public const MAX_AGE = 25;

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(!$blockReplace instanceof Water || !$this->canBeSupportedBy($blockReplace->getSide(Facing::DOWN))){
			return false;
		}

		//placing by hand doesn't give a fully grown plant, so it can still grow a bit afterwards
		$this->age = mt_rand(0, self::MAX_AGE - 1);
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		if(!$this->isCoveredWithWater() || !$this->canBeSupportedBy($this->getSide(Facing::DOWN))){
			$this->position->getWorld()->useBreakOn($this->position);
		}
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		//breaking a segment randomises the age of the one below, so the plant can keep growing
		$down = $this->getSide(Facing::DOWN);
		if($down instanceof Kelp){
			$this->position->getWorld()->setBlock($down->position, (clone $down)->setAge(mt_rand(0, self::MAX_AGE - 1)));
		}

		return parent::onBreak($item, $player, $returnedItems);
	}

	public function ticksRandomly() : bool{
		return $this->age < self::MAX_AGE;
	}

	public function onRandomTick() : void{
		if($this->age < self::MAX_AGE && mt_rand(1, 100) <= 14){
			$this->grow(null);
		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($item instanceof Fertilizer){
			if($this->seekToTip()->grow($player)){
				$item->pop();
			}
			return true;
		}
		return false;
	}

	/**
	 * Returns the topmost segment of the plant this block belongs to.
	 */
	private function seekToTip() : Kelp{
		$top = $this;
		while(($next = $top->getSide(Facing::UP)) instanceof Kelp){
			$top = $next;
		}
		return $top;
	}

	private function grow(?Player $player) : bool{
		if($this->age >= self::MAX_AGE){
			return false;
		}

		$up = $this->getSide(Facing::UP);
		if(!$up instanceof Water){
			return false;
		}

		if(!BlockEventHelper::grow($up, (clone $this)->setAge($this->age + 1), $player)){
			return false;
		}

		//the segment below the tip is always fully grown, otherwise it would grow past its neighbour i guess
		$this->position->getWorld()->setBlock($this->position, (clone $this)->setAge(self::MAX_AGE));
		return true;
	}

	private function canBeSupportedBy(Block $block) : bool{
		return $block instanceof Kelp || ($block->isSolid()
			&& $block->getTypeId() !== BlockTypeIds::MAGMA
			&& $block->getTypeId() !== BlockTypeIds::SOUL_SAND
			&& $block->getTypeId() !== BlockTypeIds::ICE);
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return [$this->asItem()];
	}
}
