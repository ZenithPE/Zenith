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

namespace pocketmine\entity\object;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\SupportType;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

class Cushion extends Living{
	private const TAG_COLOR = "Color"; //TAG_Byte

	private const SUPPORT_CHECK_PERIOD = 100; // hmm, the game only revalidates the block a cushion rests on every 5 seconds instead of every tick

	private DyeColor $color = DyeColor::WHITE;

	protected int $maxDeadTicks = 1;

	public static function getNetworkTypeId() : string{ return "minecraft:cushion"; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.249, 0.999); }

	protected function getInitialDragMultiplier() : float{ return 1.0; }

	protected function getInitialGravity() : float{ return 0.0; }

	public function getName() : string{
		return "Cushion";
	}

	public function getColor() : DyeColor{ return $this->color; }

	/** @return $this */
	public function setColor(DyeColor $color) : self{
		$this->color = $color;
		$this->networkPropertiesDirty = true;
		return $this;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(1);

		parent::initEntity($nbt);

		$colorId = $nbt->getByte(self::TAG_COLOR, DyeColorIdMap::getInstance()->toId(DyeColor::WHITE));
		//a broken colour shouldn't stop the world from loading, so it falls back instead of throwing
		$this->color = DyeColorIdMap::getInstance()->fromId($colorId) ?? DyeColor::WHITE;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_COLOR, DyeColorIdMap::getInstance()->toId($this->color));

		return $nbt;
	}

	/**
	 * @return Item[]
	 */
	public function getDrops() : array{
		if($this->lastDamageCause instanceof EntityDamageByEntityEvent){
			$killer = $this->lastDamageCause->getDamager();
			if($killer instanceof Player && !$killer->hasFiniteResources()){
				return [];
			}
		}

		return [VanillaItems::CUSHION()->setColor($this->color)];
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->getPassengers() !== []){
			return false;
		}

		//riders may hop straight from one cushion to another without standing up in between
		$vehicle = $player->getVehicle();
		if($vehicle instanceof self){
			$vehicle->removePassenger($player);
		}

		return $this->addPassenger($player);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->ticksLived % self::SUPPORT_CHECK_PERIOD < $tickDiff && !$this->hasSupportingBlock()){
			//a cushion never falls, it breaks as soon as the block holding it up is gone
			$this->kill();
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function hasSupportingBlock() : bool{
		$pos = $this->location->floor()->down();

		return $this->getWorld()->getBlock($pos)->getSupportType(Facing::UP) !== SupportType::NONE;
	}

	/**
	 * The game seats a boat rider at 1.02, which lands it a little above the boat's own position, so a
	 * cushion sitting almost flat on the floor wants a touch less than that.
	 */
	public function getSeatPosition(?Entity $passenger = null) : Vector3{
		return new Vector3(0, 1.25, 0);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		//the colour rides on the variant as an inverted dye ID, the same way a banner stores its colour
		$properties->setInt(EntityMetadataProperties::VARIANT, DyeColorIdMap::getInstance()->toInvertedId($this->color));
		$properties->setGenericFlag(EntityMetadataFlags::IMMOBILE, true);
	}
}
