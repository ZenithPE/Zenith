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

use pocketmine\block\utils\SupportType;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;

class HeavyCore extends Transparent{

	protected function recalculateCollisionBoxes() : array{
		return [
			AxisAlignedBB::one()
				->trim(Facing::UP, 8 / 16)
				->squash(Axis::X, 4 / 16)
				->squash(Axis::Z, 4 / 16)
		];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}
}
