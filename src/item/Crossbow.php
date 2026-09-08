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

use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\entity\Location;
use pocketmine\entity\object\CrossbowFireworkRocket;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\sound\CrossbowLoadingEndSound;
use pocketmine\world\sound\CrossbowShootSound;
use function cos;
use function max;
use function mt_rand;
use function sin;
use const M_PI;

class Crossbow extends Tool implements Releasable{

	private const TAG_CHARGED_ITEM = "chargedItem";
	private const TAG_CHARGED_ITEM_NAME = "Name";
	private const TAG_CHARGED_ITEM_COUNT = "Count";
	private const TAG_CHARGED_ITEM_DAMAGE = "Damage";
	private const TAG_CHARGED_ITEM_TAG = "tag";

	/**
	 * Time the crossbow has to be held down before it becomes loaded, before Quick Charge is applied.
	 */
	public const CHARGE_DURATION = 25;

	/**
	 * Ticks removed from the charge duration by each level of Quick Charge.
	 */
	public const QUICK_CHARGE_REDUCTION = 5;

	public const SHOOT_FORCE = 3.15;

	public const FIREWORK_SHOOT_FORCE = 1.6;

	/**
	 * A firework rocket fired from a crossbow explodes on its own after this many ticks, plus a random extra.
	 */
	private const FIREWORK_FLIGHT_TIME = 10;

	private const FIREWORK_FLIGHT_TIME_RANDOM = 12;

	/**
	 * Angle in degrees between the middle projectile and each of the two side ones fired by Multishot.
	 */
	private const MULTISHOT_ANGLE = 10.0;

	private const DURABILITY_COST = 1;

	private const DURABILITY_COST_MULTISHOT = 3;

	private const DURABILITY_COST_FIREWORK = 3;

	private ?Item $chargedItem = null;

	public function getFuelTime() : int{
		return 200;
	}

	public function getMaxDurability() : int{
		return 465;
	}

	public function isCharged() : bool{
		return $this->chargedItem !== null;
	}

	/**
	 * Returns the ammo the crossbow is loaded with, or null if it isn't loaded.
	 */
	public function getChargedItem() : ?Item{
		return $this->chargedItem === null ? null : clone $this->chargedItem;
	}

	/** @return $this */
	public function setChargedItem(?Item $item) : self{
		if($item !== null && !self::isAmmo($item)){
			throw new \InvalidArgumentException("A crossbow can only be charged with an arrow or a firework rocket");
		}
		$this->chargedItem = $item === null ? null : (clone $item)->setCount(1);
		return $this;
	}

	public static function isAmmo(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::ARROW || $item instanceof FireworkRocket;
	}

	protected function deserializeCompoundTag(CompoundTag $tag) : void{
		parent::deserializeCompoundTag($tag);

		$this->chargedItem = null;

		$chargedItem = $tag->getCompoundTag(self::TAG_CHARGED_ITEM);
		if($chargedItem === null || $chargedItem->getByte(self::TAG_CHARGED_ITEM_COUNT, 0) <= 0){
			return;
		}

		$item = $chargedItem->getString(self::TAG_CHARGED_ITEM_NAME, ItemTypeNames::ARROW) === ItemTypeNames::FIREWORK_ROCKET ?
			VanillaItems::FIREWORK_ROCKET() :
			VanillaItems::ARROW();

		$itemTag = $chargedItem->getCompoundTag(self::TAG_CHARGED_ITEM_TAG);
		if($itemTag !== null){
			$item->setNamedTag($itemTag);
		}

		$this->chargedItem = $item;
	}

	protected function serializeCompoundTag(CompoundTag $tag) : void{
		parent::serializeCompoundTag($tag);

		if($this->chargedItem === null){
			$tag->removeTag(self::TAG_CHARGED_ITEM);
			return;
		}

		//the client renders the loaded crossbow from this tag
		$chargedItem = CompoundTag::create()
			->setByte(self::TAG_CHARGED_ITEM_COUNT, 1)
			->setShort(self::TAG_CHARGED_ITEM_DAMAGE, 0)
			->setString(self::TAG_CHARGED_ITEM_NAME, $this->chargedItem instanceof FireworkRocket ? ItemTypeNames::FIREWORK_ROCKET : ItemTypeNames::ARROW);

		//keeps the explosions of a loaded firework rocket
		$itemTag = $this->chargedItem->getNamedTag();
		if($itemTag->count() > 0){
			$chargedItem->setTag(self::TAG_CHARGED_ITEM_TAG, $itemTag);
		}

		$tag->setTag(self::TAG_CHARGED_ITEM, $chargedItem);
	}

	public function canStartUsingItem(Player $player) : bool{
		return !$this->isCharged() && ($this->findAmmo($player) !== null || !$player->hasFiniteResources());
	}

	public function getMinUseDuration() : int{
		return $this->getChargeDuration();
	}

	/**
	 * Returns how long the crossbow has to be held down before it becomes loaded, Quick Charge included.
	 */
	public function getChargeDuration() : int{
		$quickCharge = $this->getEnchantmentLevel(VanillaEnchantments::QUICK_CHARGE());

		return max(0, self::CHARGE_DURATION - ($quickCharge * self::QUICK_CHARGE_REDUCTION));
	}

	public function onReleaseUsing(Player $player, array &$returnedItems) : ItemUseResult{
		if($this->isCharged() || $player->getItemUseDuration() < $this->getChargeDuration()){
			return ItemUseResult::NONE;
		}

		$ammo = $this->findAmmo($player);
		if(!$player->hasFiniteResources()){
			$this->setChargedItem($ammo === null ? VanillaItems::ARROW() : $ammo[1]);
		}else{
			if($ammo === null){
				return ItemUseResult::FAIL;
			}

			[$inventory, $item] = $ammo;
			$this->setChargedItem($item);
			$inventory->removeItem((clone $item)->setCount(1));
		}

		$player->getWorld()->addSound($player->getPosition(), new CrossbowLoadingEndSound());

		return ItemUseResult::SUCCESS;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$chargedItem = $this->chargedItem;
		if($chargedItem === null){
			return ItemUseResult::NONE;
		}

		$multishot = $this->hasEnchantment(VanillaEnchantments::MULTISHOT());
		$angles = $multishot ? [-self::MULTISHOT_ANGLE, 0.0, self::MULTISHOT_ANGLE] : [0.0];

		$fired = 0;
		foreach($angles as $angle){
			if($this->shoot($player, $chargedItem, $angle)){
				$fired++;
			}
		}

		if($fired === 0){
			return ItemUseResult::FAIL;
		}

		$this->chargedItem = null;
		if($player->hasFiniteResources()){
			$this->applyDamage(match(true){
				$chargedItem instanceof FireworkRocket => self::DURABILITY_COST_FIREWORK,
				$multishot => self::DURABILITY_COST_MULTISHOT,
				default => self::DURABILITY_COST
			});
		}

		return ItemUseResult::SUCCESS;
	}

	/**
	 * Fires a single projectile, rotated horizontally by the given angle in degrees.
	 */
	private function shoot(Player $player, Item $ammo, float $angle) : bool{
		$location = $player->getLocation();
		$yaw = $location->yaw + $angle;
		$origin = Location::fromObject(
			$player->getEyePos(),
			$player->getWorld(),
			($yaw > 180 ? 360 : 0) - $yaw,
			-$location->pitch
		);
		$direction = $this->getDirectionVector($yaw, $location->pitch);

		if($ammo instanceof FireworkRocket){
			$entity = new CrossbowFireworkRocket($origin, self::FIREWORK_FLIGHT_TIME + mt_rand(0, self::FIREWORK_FLIGHT_TIME_RANDOM), $ammo->getExplosions());
			$entity->setOwningEntity($player);
			$entity->setMotion($direction->multiply(self::FIREWORK_SHOOT_FORCE));
			$entity->spawnToAll();
			$player->getWorld()->addSound($location, new CrossbowShootSound());

			return true;
		}

		$entity = new ArrowEntity($origin, $player, true);
		$entity->setMotion($direction);
		$entity->setPiercing($this->getEnchantmentLevel(VanillaEnchantments::PIERCING()));

		$ev = new EntityShootBowEvent($player, $this, $entity, self::SHOOT_FORCE);
		if($player->isSpectator()){
			$ev->cancel();
		}
		$ev->call();

		$entity = $ev->getProjectile(); //this might have been changed by plugins

		if($ev->isCancelled()){
			$entity->flagForDespawn();
			return false;
		}

		$entity->setMotion($entity->getMotion()->multiply($ev->getForce()));

		if($entity instanceof Projectile){
			$projectileEv = new ProjectileLaunchEvent($entity);
			$projectileEv->call();
			if($projectileEv->isCancelled()){
				$entity->flagForDespawn();
				return false;
			}
		}

		$entity->spawnToAll();
		$player->getWorld()->addSound($location, new CrossbowShootSound());

		return true;
	}

	private function getDirectionVector(float $yaw, float $pitch) : Vector3{
		$pitchRad = $pitch * (M_PI / 180);
		$yawRad = $yaw * (M_PI / 180);

		return (new Vector3(
			-sin($yawRad) * cos($pitchRad),
			-sin($pitchRad),
			cos($yawRad) * cos($pitchRad)
		))->normalize();
	}

	/**
	 * Returns the inventory holding the first usable ammo and the ammo itself, or null if the player has none.
	 * The offhand is searched first, like vanilla does.
	 *
	 * @phpstan-return array{Inventory, Item}|null
	 */
	private function findAmmo(Player $player) : ?array{
		foreach([$player->getOffHandInventory(), $player->getInventory()] as $inventory){
			foreach($inventory->getContents() as $item){
				if(self::isAmmo($item)){
					return [$inventory, $item];
				}
			}
		}

		return null;
	}
}
