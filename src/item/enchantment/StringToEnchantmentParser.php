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

namespace pocketmine\item\enchantment;

use pocketmine\utils\SingletonTrait;
use pocketmine\utils\StringToTParser;

/**
 * Handles parsing enchantments from strings. This is used to interpret names in the /enchant command.
 *
 * @phpstan-extends StringToTParser<Enchantment>
 */
final class StringToEnchantmentParser extends StringToTParser{
	use SingletonTrait;

	private static function make() : self{
		$result = new self();

		$result->register("blast_protection", fn() => VanillaEnchantments::BLAST_PROTECTION());
		$result->register("breach", fn() => VanillaEnchantments::BREACH());
		$result->register("density", fn() => VanillaEnchantments::DENSITY());
		$result->register("efficiency", fn() => VanillaEnchantments::EFFICIENCY());
		$result->register("feather_falling", fn() => VanillaEnchantments::FEATHER_FALLING());
		$result->register("fire_aspect", fn() => VanillaEnchantments::FIRE_ASPECT());
		$result->register("fire_protection", fn() => VanillaEnchantments::FIRE_PROTECTION());
		$result->register("flame", fn() => VanillaEnchantments::FLAME());
		$result->register("fortune", fn() => VanillaEnchantments::FORTUNE());
		$result->register("frost_walker", fn() => VanillaEnchantments::FROST_WALKER());
		$result->register("infinity", fn() => VanillaEnchantments::INFINITY());
		$result->register("knockback", fn() => VanillaEnchantments::KNOCKBACK());
		$result->register("lunge", fn() => VanillaEnchantments::LUNGE());
		$result->register("mending", fn() => VanillaEnchantments::MENDING());
		$result->register("multishot", fn() => VanillaEnchantments::MULTISHOT());
		$result->register("piercing", fn() => VanillaEnchantments::PIERCING());
		$result->register("power", fn() => VanillaEnchantments::POWER());
		$result->register("quick_charge", fn() => VanillaEnchantments::QUICK_CHARGE());
		$result->register("projectile_protection", fn() => VanillaEnchantments::PROJECTILE_PROTECTION());
		$result->register("protection", fn() => VanillaEnchantments::PROTECTION());
		$result->register("punch", fn() => VanillaEnchantments::PUNCH());
		$result->register("respiration", fn() => VanillaEnchantments::RESPIRATION());
		$result->register("aqua_affinity", fn() => VanillaEnchantments::AQUA_AFFINITY());
		$result->register("sharpness", fn() => VanillaEnchantments::SHARPNESS());
		$result->register("silk_touch", fn() => VanillaEnchantments::SILK_TOUCH());
		$result->register("swift_sneak", fn() => VanillaEnchantments::SWIFT_SNEAK());
		$result->register("thorns", fn() => VanillaEnchantments::THORNS());
		$result->register("unbreaking", fn() => VanillaEnchantments::UNBREAKING());
		$result->register("vanishing", fn() => VanillaEnchantments::VANISHING());
		$result->register("wind_burst", fn() => VanillaEnchantments::WIND_BURST());

		return $result;
	}

	public function parse(string $input) : ?Enchantment{
		return parent::parse($input);
	}
}
