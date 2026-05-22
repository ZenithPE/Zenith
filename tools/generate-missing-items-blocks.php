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

namespace pocketmine\missing_items_blocks;

use pocketmine\item\StringToItemParser;
use pocketmine\utils\StringToTParser;
use ReflectionProperty;
use function array_diff;
use function array_keys;
use function array_map;
use function count;
use function file_get_contents;
use function fwrite;
use function json_decode;
use function sort;
use function str_replace;
use const JSON_THROW_ON_ERROR;
use const STDERR;
use const STDOUT;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * This script dumps vanilla item/block IDs that are not registered in Zenith.
 *
 * Usage: php generate-missing-items-blocks.php [--blocks] [--items] [--all]
 *   --blocks  Show missing blocks only
 *   --items   Show missing items only
 *   --all     Show both (default)
 */

$showBlocks = false;
$showItems = false;

foreach(array_slice($argv ?? [], 1) as $arg){
	match($arg){
		'--blocks' => $showBlocks = true,
		'--items'  => $showItems = true,
		'--all'    => ($showBlocks = $showItems = true),
		default    => (function() use($arg) : void {
			fwrite(STDERR, "Unknown argument: $arg\n");
			fwrite(STDERR, "Usage: php generate-missing-items-blocks.php [--blocks] [--items] [--all]\n");
			exit(1);
		})()
	};
}

if(!$showBlocks && !$showItems){
	$showBlocks = $showItems = true;
}

$callbackMapRef = new ReflectionProperty(StringToTParser::class, 'callbackMap');
$callbackMapRef->setAccessible(true);

$vanillaRaw = json_decode(
	file_get_contents(dirname(__DIR__) . '/vendor/pocketmine/bedrock-data/required_item_list.json'),
	true,
	flags: JSON_THROW_ON_ERROR
);

$vanillaIds = array_map(
	static fn(string $id) : string => str_replace('minecraft:', '', $id),
	array_keys($vanillaRaw)
);
sort($vanillaIds);

if($showItems){
	$pmmpItemIds = array_keys($callbackMapRef->getValue(StringToItemParser::getInstance()));

	$missingItems = array_diff($vanillaIds, $pmmpItemIds);
	sort($missingItems);

	fwrite(STDOUT, "=== Missing Items (" . count($missingItems) . " / " . count($vanillaIds) . " vanilla) ===\n");
	foreach($missingItems as $id){
		fwrite(STDOUT, "  minecraft:$id\n");
	}
	fwrite(STDOUT, "\n");
}

if($showBlocks){
	$itemParser = StringToItemParser::getInstance();
	$pmmpItemIds = array_keys($callbackMapRef->getValue($itemParser));

	$vanillaBlockIds = array_map(
		static fn(string $id) : string => str_replace('minecraft:', '', $id),
		array_keys(json_decode(
			file_get_contents(dirname(__DIR__) . '/vendor/pocketmine/bedrock-data/block_id_to_item_id_map.json'),
			true,
			flags: JSON_THROW_ON_ERROR
		))
	);
	sort($vanillaBlockIds);

	$missingBlocks = array_diff($vanillaBlockIds, $pmmpItemIds);
	sort($missingBlocks);

	fwrite(STDOUT, "=== Missing Blocks (" . count($missingBlocks) . " / " . count($vanillaBlockIds) . " vanilla) ===\n");
	foreach($missingBlocks as $id){
		fwrite(STDOUT, "  minecraft:$id\n");
	}
	fwrite(STDOUT, "\n");
}
