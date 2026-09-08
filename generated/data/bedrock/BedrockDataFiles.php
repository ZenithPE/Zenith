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

namespace pocketmine\data\bedrock;

use const pocketmine\BEDROCK_DATA_PATH;

final class BedrockDataFiles{
	private function __construct(){
		//NOOP
	}

	public const BANNER_PATTERNS_JSON = BEDROCK_DATA_PATH . '/banner_patterns.json';
	public const BIOME_DEFINITIONS_NBT = BEDROCK_DATA_PATH . '/biome_definitions.nbt';
	public const BIOME_ID_MAP_JSON = BEDROCK_DATA_PATH . '/biome_id_map.json';
	public const BIOMES_JSON = BEDROCK_DATA_PATH . '/biomes.json';
	public const BLOCK_DEFINITIONS_NBT = BEDROCK_DATA_PATH . '/block_definitions.nbt';
	public const BLOCK_ID_TO_ITEM_ID_MAP_JSON = BEDROCK_DATA_PATH . '/block_id_to_item_id_map.json';
	public const BLOCK_PALETTE_NBT = BEDROCK_DATA_PATH . '/block_palette.nbt';
	public const BLOCK_PROPERTIES_TABLE_JSON = BEDROCK_DATA_PATH . '/block_properties_table.json';
	public const BLOCK_STATE_META_MAP_JSON = BEDROCK_DATA_PATH . '/block_state_meta_map.json';
	public const BLOCK_TAGS_JSON = BEDROCK_DATA_PATH . '/block_tags.json';
	public const CAMERA_AIM_ASSIST_PRESETS_NBT = BEDROCK_DATA_PATH . '/camera_aim_assist_presets.nbt';
	public const CAMERA_PRESETS_NBT = BEDROCK_DATA_PATH . '/camera_presets.nbt';
	public const COMMAND_ARG_TYPES_JSON = BEDROCK_DATA_PATH . '/command_arg_types.json';
	public const CREATIVE_ITEMS_JSON = BEDROCK_DATA_PATH . '/creative_items.json';
	public const ENTITY_ID_MAP_JSON = BEDROCK_DATA_PATH . '/entity_id_map.json';
	public const ENTITY_IDENTIFIERS_NBT = BEDROCK_DATA_PATH . '/entity_identifiers.nbt';
	public const ENUMS = BEDROCK_DATA_PATH . '/enums';
	public const ENUMS_PY = BEDROCK_DATA_PATH . '/enums.py';
	public const ITEM_COMPONENTS_NBT = BEDROCK_DATA_PATH . '/item_components.nbt';
	public const ITEM_PALETTE_JSON = BEDROCK_DATA_PATH . '/item_palette.json';
	public const ITEM_TAGS_JSON = BEDROCK_DATA_PATH . '/item_tags.json';
	public const LEVEL_SOUND_ID_MAP_JSON = BEDROCK_DATA_PATH . '/level_sound_id_map.json';
	public const PROTOCOL_INFO_JSON = BEDROCK_DATA_PATH . '/protocol_info.json';
	public const R12_TO_CURRENT_BLOCK_MAP_BIN = BEDROCK_DATA_PATH . '/r12_to_current_block_map.bin';
	public const R16_TO_CURRENT_ITEM_MAP_JSON = BEDROCK_DATA_PATH . '/r16_to_current_item_map.json';
	public const RECIPES_JSON = BEDROCK_DATA_PATH . '/recipes.json';
	public const TRIM_DATA_JSON = BEDROCK_DATA_PATH . '/trim_data.json';
	public const VOXEL_SHAPES_JSON = BEDROCK_DATA_PATH . '/voxel_shapes.json';
}
