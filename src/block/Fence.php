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

use pocketmine\block\utils\HorizontalConnections;
use pocketmine\block\utils\HorizontalConnectionsTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use function count;

class Fence extends Transparent implements HorizontalConnections{
	use HorizontalConnectionsTrait;

	public function getThickness() : float{
		return 0.25;
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();

		$this->collisionBoxes = null;

		return $this;
	}

	public function onNearbyBlockChange() : void{
		if($this->recalculateConnections()){
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	protected function canConnectTo(int $facing) : bool{
		$block = $this->getSide($facing);
		return $block instanceof static || $block instanceof FenceGate || $block->getSupportType(Facing::opposite($facing)) === SupportType::FULL;
	}

	protected function recalculateCollisionBoxes() : array{
		$inset = 0.5 - $this->getThickness() / 2;

		$bbs = [];

		$connectWest = isset($this->connections[Facing::WEST]);
		$connectEast = isset($this->connections[Facing::EAST]);

		if($connectWest || $connectEast){
			//X axis (west/east)
			$bbs[] = AxisAlignedBB::one()
				->squash(Axis::Z, $inset)
				->extend(Facing::UP, 0.5)
				->trim(Facing::WEST, $connectWest ? 0 : $inset)
				->trim(Facing::EAST, $connectEast ? 0 : $inset);
		}

		$connectNorth = isset($this->connections[Facing::NORTH]);
		$connectSouth = isset($this->connections[Facing::SOUTH]);

		if($connectNorth || $connectSouth){
			//Z axis (north/south)
			$bbs[] = AxisAlignedBB::one()
				->squash(Axis::X, $inset)
				->extend(Facing::UP, 0.5)
				->trim(Facing::NORTH, $connectNorth ? 0 : $inset)
				->trim(Facing::SOUTH, $connectSouth ? 0 : $inset);
		}

		if(count($bbs) === 0){
			//centre post AABB (only needed if not connected on any axis - other BBs overlapping will do this if any connections are made)
			return [
				AxisAlignedBB::one()
					->extend(Facing::UP, 0.5)
					->contract($inset, 0, $inset)
			];
		}

		return $bbs;
	}

	public function getSupportType(int $facing) : SupportType{
		return Facing::axis($facing) === Axis::Y ? SupportType::CENTER : SupportType::NONE;
	}
}
