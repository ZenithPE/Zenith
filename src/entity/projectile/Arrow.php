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

namespace pocketmine\entity\projectile;

use pocketmine\block\Block;
use pocketmine\entity\animation\ArrowShakeAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\RayTraceResult;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\ArrowHitSound;
use function ceil;
use function count;
use function mt_rand;
use function sqrt;

class Arrow extends Projectile{

	public static function getNetworkTypeId() : string{ return EntityIds::ARROW; }

	public const PICKUP_NONE = 0;
	public const PICKUP_ANY = 1;
	public const PICKUP_CREATIVE = 2;

	/**
	 * Speed kept by a piercing arrow after going through an entity.
	 */
	private const PIERCING_SPEED_MULTIPLIER = 0.99;

	private const TAG_PICKUP = "pickup"; //TAG_Byte
	public const TAG_CRIT = "crit"; //TAG_Byte
	private const TAG_LIFE = "life"; //TAG_Short

	protected float $damage = 2.0;
	protected int $pickupMode = self::PICKUP_ANY;
	protected float $punchKnockback = 0.0;
	protected int $collideTicks = 0;
	protected bool $critical = false;
	protected int $piercing = 0;

	/**
	 * Entities the arrow already went through, so that it doesn't damage them again on the next tick.
	 *
	 * @var true[]
	 * @phpstan-var array<int, true>
	 */
	protected array $piercedEntities = [];

	public function __construct(Location $location, ?Entity $shootingEntity, bool $critical, ?CompoundTag $nbt = null){
		parent::__construct($location, $shootingEntity, $nbt);
		$this->setCritical($critical);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.25, 0.25); }

	protected function getInitialDragMultiplier() : float{ return 0.01; }

	protected function getInitialGravity() : float{ return 0.05; }

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->pickupMode = $nbt->getByte(self::TAG_PICKUP, self::PICKUP_ANY);
		$this->critical = $nbt->getByte(self::TAG_CRIT, 0) === 1;
		$this->collideTicks = $nbt->getShort(self::TAG_LIFE, $this->collideTicks);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_PICKUP, $this->pickupMode);
		$nbt->setByte(self::TAG_CRIT, $this->critical ? 1 : 0);
		$nbt->setShort(self::TAG_LIFE, $this->collideTicks);
		return $nbt;
	}

	public function isCritical() : bool{
		return $this->critical;
	}

	public function setCritical(bool $value = true) : void{
		$this->critical = $value;
		$this->networkPropertiesDirty = true;
	}

	public function getResultDamage() : int{
		$base = (int) ceil($this->motion->length() * parent::getResultDamage());
		if($this->isCritical()){
			return ($base + mt_rand(0, (int) ($base / 2) + 1));
		}else{
			return $base;
		}
	}

	public function getPunchKnockback() : float{
		return $this->punchKnockback;
	}

	public function setPunchKnockback(float $punchKnockback) : void{
		$this->punchKnockback = $punchKnockback;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->closed){
			return false;
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->blockHit !== null){
			$this->collideTicks += $tickDiff;
			if($this->collideTicks > 1200){
				$this->flagForDespawn();
				$hasUpdate = true;
			}
		}else{
			$this->collideTicks = 0;
		}

		return $hasUpdate;
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		$this->setCritical(false);
		$this->broadcastSound(new ArrowHitSound());
	}

	protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void{
		parent::onHitBlock($blockHit, $hitResult);
		$this->broadcastAnimation(new ArrowShakeAnimation($this, 7));
	}

	/**
	 * Returns how many entities the arrow can go through before stopping.
	 */
	public function getPiercing() : int{
		return $this->piercing;
	}

	public function setPiercing(int $piercing) : void{
		if($piercing < 0){
			throw new \InvalidArgumentException("Piercing must not be negative");
		}
		$this->piercing = $piercing;
	}

	public function canCollideWith(Entity $entity) : bool{
		return !isset($this->piercedEntities[$entity->getId()]) && parent::canCollideWith($entity);
	}

	protected function despawnsOnEntityHit() : bool{
		return count($this->piercedEntities) > $this->piercing;
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		$this->piercedEntities[$entityHit->getId()] = true;

		parent::onHitEntity($entityHit, $hitResult);

		if(!$this->isFlaggedForDespawn()){
			//without this the projectile code would zero the motion, stopping the arrow inside the entity it just went through
			$this->motion = $this->motion->multiply(self::PIERCING_SPEED_MULTIPLIER);
		}

		if($this->punchKnockback > 0){
			$horizontalSpeed = sqrt($this->motion->x ** 2 + $this->motion->z ** 2);
			if($horizontalSpeed > 0){
				$multiplier = $this->punchKnockback * 0.6 / $horizontalSpeed;
				$entityHit->setMotion($entityHit->getMotion()->add($this->motion->x * $multiplier, 0.1, $this->motion->z * $multiplier));
			}
		}
	}

	public function getPickupMode() : int{
		return $this->pickupMode;
	}

	public function setPickupMode(int $pickupMode) : void{
		$this->pickupMode = $pickupMode;
	}

	public function onCollideWithPlayer(Player $player) : void{
		if($this->blockHit === null){
			return;
		}

		$item = VanillaItems::ARROW();
		$playerInventory = match(true){
			!$player->hasFiniteResources() => null, //arrows are not picked up in creative
			$player->getOffHandInventory()->getItem(0)->canStackWith($item) && $player->getOffHandInventory()->canAddItem($item) => $player->getOffHandInventory(),
			$player->getInventory()->canAddItem($item) => $player->getInventory(),
			default => null
		};

		$ev = new EntityItemPickupEvent($player, $this, $item, $playerInventory);
		if($player->hasFiniteResources() && $playerInventory === null){
			$ev->cancel();
		}
		if($this->pickupMode === self::PICKUP_NONE || ($this->pickupMode === self::PICKUP_CREATIVE && !$player->isCreative())){
			$ev->cancel();
		}

		$ev->call();
		if($ev->isCancelled()){
			return;
		}

		NetworkBroadcastUtils::broadcastEntityEvent(
			$this->getViewers(),
			fn(EntityEventBroadcaster $broadcaster, array $recipients) => $broadcaster->onPickUpItem($recipients, $player, $this)
		);

		$ev->getInventory()?->addItem($ev->getItem());
		$this->flagForDespawn();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::CRITICAL, $this->critical);
	}
}
