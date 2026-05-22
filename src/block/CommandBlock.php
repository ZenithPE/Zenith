<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\tile\CommandBlock as TileCommandBlock;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class CommandBlock extends Opaque{
	use AnyFacingTrait;

	private bool $conditional = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->conditional);
	}

	public function isConditional() : bool{ return $this->conditional; }

	/** @return $this */
	public function setConditional(bool $conditional) : self{
		$this->conditional = $conditional;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->facing = $face;
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onScheduledUpdate() : void{
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof TileCommandBlock && $tile->isAuto()){
			$tile->execute();
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player === null){
			return false;
		}

		if(!$player->isOp() && !$player->isCreative()){
			return false;
		}

		$tile = $this->position->getWorld()->getTile($this->position);
		if(!$tile instanceof TileCommandBlock){
			return false;
		}

		$packet = ContainerOpenPacket::blockInv(
			-1,
			WindowTypes::COMMAND_BLOCK,
			new BlockPosition(
				$this->position->getFloorX(),
				$this->position->getFloorY(),
				$this->position->getFloorZ()
			)
		);

		$player->getNetworkSession()->sendDataPacket($packet);
		return true;
	}

	public function getDrops(Item $item) : array{
		return [];
	}

	public function isAffectedBySilkTouch() : bool{
		return false;
	}
}
