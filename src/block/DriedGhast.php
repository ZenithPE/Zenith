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

use pocketmine\block\utils\CoveredWithWaterTrait;
use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\data\runtime\RuntimeDataDescriber;

class DriedGhast extends Opaque implements HorizontalFacing{
	use CoveredWithWaterTrait;
	use FacesOppositePlacingPlayerTrait;

	public const MAX_REHYDRATION_LEVEL = 3;

	private const REHYDRATION_STEP_TICKS = 20 * 60 * 20 / (self::MAX_REHYDRATION_LEVEL + 1);

	protected int $rehydrationLevel = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->boundedIntAuto(0, self::MAX_REHYDRATION_LEVEL, $this->rehydrationLevel);
	}

	public function getRehydrationLevel() : int{
		return $this->rehydrationLevel;
	}

	/** @return $this */
	public function setRehydrationLevel(int $rehydrationLevel) : self{
		if($rehydrationLevel < 0 || $rehydrationLevel > self::MAX_REHYDRATION_LEVEL){
			throw new \InvalidArgumentException("Rehydration level must be in range 0 ... " . self::MAX_REHYDRATION_LEVEL);
		}
		$this->rehydrationLevel = $rehydrationLevel;
		return $this;
	}

	public function onPostPlace() : void{
		$this->scheduleNextStep();
	}

	public function onNearbyBlockChange() : void{
		$this->scheduleNextStep();
	}

	public function onScheduledUpdate() : void{
		if($this->isCoveredWithWater()){
			if($this->rehydrationLevel < self::MAX_REHYDRATION_LEVEL){
				$this->position->getWorld()->setBlock($this->position, (clone $this)->setRehydrationLevel($this->rehydrationLevel + 1));
			}
			//TODO: a fully rehydrated ghast should hatch into a ghastling, but we don't have the entity yet
		}elseif($this->rehydrationLevel > 0){
			$this->position->getWorld()->setBlock($this->position, (clone $this)->setRehydrationLevel($this->rehydrationLevel - 1));
		}
	}

	private function scheduleNextStep() : void{
		//a dry ghast out of water has nothing left to do, right?
		if($this->isCoveredWithWater() || $this->rehydrationLevel > 0){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, self::REHYDRATION_STEP_TICKS);
		}
	}
}
