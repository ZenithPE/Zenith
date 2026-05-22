<?php

declare(strict_types=1);

namespace pocketmine\inventory;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\block\inventory\BlockInventoryTrait;
use pocketmine\world\Position;

class CommandBlockInventory extends SimpleInventory implements BlockInventory {

	use BlockInventoryTrait;

	public function __construct(Position $holder, int $size){
		$this->holder = $holder;
		parent::__construct($size);
	}
}
