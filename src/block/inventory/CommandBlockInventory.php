<?php

declare(strict_types=1);

namespace pocketmine\block\inventory;

use pocketmine\inventory\SimpleInventory;
use pocketmine\world\Position;

class CommandBlockInventory extends SimpleInventory implements BlockInventory {

	use BlockInventoryTrait;

	public function __construct(Position $holder, int $size){
		$this->holder = $holder;
		parent::__construct($size);
	}
}
