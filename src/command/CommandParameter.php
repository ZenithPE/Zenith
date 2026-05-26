<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command;

use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use function array_map;
use function in_array;
use function is_numeric;
use function strtolower;
use const PHP_FLOAT_MAX;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

class CommandParameter{

	private function __construct(
		public readonly string $name,
		public readonly CommandParamType $type,
		public readonly bool $optional = false,
		public readonly ?CommandEnum $enumData = null,
		public readonly int $minInt = PHP_INT_MIN,
		public readonly int $maxInt = PHP_INT_MAX,
		public readonly float $minFloat = -PHP_FLOAT_MAX,
		public readonly float $maxFloat = PHP_FLOAT_MAX
	){}

	public static function int(string $name, bool $optional = false, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX) : self{
		return new self($name, CommandParamType::INT, $optional, null, $min, $max);
	}

	public static function float(string $name, bool $optional = false, float $min = -PHP_FLOAT_MAX, float $max = PHP_FLOAT_MAX) : self{
		return new self($name, CommandParamType::FLOAT, $optional, minFloat: $min, maxFloat: $max);
	}

	public static function string(string $name, bool $optional = false) : self{
		return new self($name, CommandParamType::STRING, $optional);
	}

	public static function bool(string $name, bool $optional = false) : self{
		return new self($name, CommandParamType::BOOLEAN, $optional);
	}

	public static function target(string $name, bool $optional = false) : self{
		return new self($name, CommandParamType::TARGET, $optional);
	}

	public static function text(string $name, bool $optional = false) : self{
		return new self($name, CommandParamType::TEXT, $optional);
	}

	public static function enum(string $name, CommandEnum $enum, bool $optional = false) : self{
		return new self($name, CommandParamType::ENUM, $optional, $enum);
	}

	public static function item(string $name, bool $optional = false) : self{
		return new self($name, CommandParamType::ITEM, $optional);
	}

	public function validate(string $argument, CommandSender $sender) : bool{
		return match($this->type){
			CommandParamType::INT =>
				is_numeric($argument) && (int) $argument >= $this->minInt && (int) $argument <= $this->maxInt,
			CommandParamType::FLOAT =>
				is_numeric($argument) && (float) $argument >= $this->minFloat && (float) $argument <= $this->maxFloat,
			CommandParamType::STRING, CommandParamType::TEXT =>
				$argument !== "",
			CommandParamType::BOOLEAN =>
				in_array(strtolower($argument), ["true", "false", "yes", "no", "1", "0", "on", "off"], true),
			CommandParamType::TARGET =>
				$argument === "@s" ? $sender instanceof Player : $sender->getServer()->getPlayerByPrefix($argument) !== null,
			CommandParamType::ENUM =>
				$this->enumData !== null &&
				in_array(strtolower($argument), array_map(strtolower(...), $this->enumData->getValues()), true),
			CommandParamType::ITEM =>
				StringToItemParser::getInstance()->parse($argument) !== null ||
				self::tryLegacyItem($argument),
		};
	}

	public function parse(string $argument, CommandSender $sender) : mixed{
		return match($this->type){
			CommandParamType::INT => (int) $argument,
			CommandParamType::FLOAT => (float) $argument,
			CommandParamType::STRING, CommandParamType::TEXT => $argument,
			CommandParamType::BOOLEAN => in_array(strtolower($argument), ["true", "yes", "1", "on"], true),
			CommandParamType::TARGET =>
				$argument === "@s" && $sender instanceof Player
					? $sender
					: $sender->getServer()->getPlayerByPrefix($argument),
			CommandParamType::ENUM => strtolower($argument),
			CommandParamType::ITEM => $argument,
		};
	}

	private static function tryLegacyItem(string $argument) : bool{
		try{
			LegacyStringToItemParser::getInstance()->parse($argument);
			return true;
		}catch(LegacyStringToItemParserException){
			return false;
		}
	}

	public function getUsageText() : string{
		$typeName = match($this->type){
			CommandParamType::ENUM => $this->enumData?->getName() ?? "enum",
			CommandParamType::ITEM => "Item",
			default => $this->type->value,
		};
		$inner = "{$this->name}: {$typeName}";
		return $this->optional ? "[{$inner}]" : "<{$inner}>";
	}
}
