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

namespace pocketmine\network\mcpe\cache;

use pocketmine\color\Color;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\BiomeDefinitionListPacket;
use pocketmine\network\mcpe\protocol\types\biome\BiomeDefinitionEntry;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\SerializableVoxelCells;
use pocketmine\network\mcpe\protocol\types\SerializableVoxelShape;
use pocketmine\network\mcpe\protocol\VoxelShapesPacket;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use function zlib_decode;

class StaticPacketCache{
	use SingletonTrait;

	private static function loadCompoundFromFile(string $filePath) : CompoundTag{
		$raw = zlib_decode(Filesystem::fileGetContents($filePath));
		if($raw === false){
			throw new SavedDataLoadingException("Failed to decompress $filePath");
		}
		return (new BigEndianNbtSerializer())->read($raw)->mustGetCompoundTag();
	}

	/**
	 * Loads biome definitions from a bedrock-network-data biome_definitions.nbt, which stores the definitions in the
	 * same string-pooled layout as BiomeDefinitionListPacket.
	 *
	 * @return list<BiomeDefinitionEntry>
	 */
	private static function loadBiomeDefinitionModel(string $filePath) : array{
		$root = self::loadCompoundFromFile($filePath);

		$stringListTag = $root->getListTag("biomeStringList") ?? throw new SavedDataLoadingException("$filePath missing biomeStringList");
		$strings = [];
		foreach($stringListTag as $i => $tag){
			if(!($tag instanceof StringTag)){
				throw new SavedDataLoadingException("biomeStringList should only contain strings");
			}
			$strings[$i] = $tag->getValue();
		}
		$locateString = function(int $index) use ($strings, $filePath) : string{
			return $strings[$index] ?? throw new SavedDataLoadingException("$filePath refers to unknown string index $index");
		};

		$biomeDataTag = $root->getListTag("biomeData") ?? throw new SavedDataLoadingException("$filePath missing biomeData");
		$entries = [];
		foreach($biomeDataTag as $entryTag){
			if(!($entryTag instanceof CompoundTag)){
				throw new SavedDataLoadingException("biomeData should only contain compounds");
			}
			$data = $entryTag->getCompoundTag("data") ?? throw new SavedDataLoadingException("Biome entry is missing data");

			$tags = null;
			$tagsTag = $data->getCompoundTag("tags")?->getListTag("tags");
			if($tagsTag !== null){
				$tags = [];
				foreach($tagsTag as $tagIndexTag){
					if(!($tagIndexTag instanceof ShortTag)){
						throw new SavedDataLoadingException("Biome tag list should only contain shorts");
					}
					$tags[] = $locateString($tagIndexTag->getValue() & 0xffff);
				}
			}

			$entries[] = new BiomeDefinitionEntry(
				$locateString($entryTag->getShort("index") & 0xffff),
				$data->getShort("id") & 0xffff,
				$data->getFloat("temperature"),
				$data->getFloat("downfall"),
				$data->getFloat("foliageSnow"),
				$data->getFloat("depth"),
				$data->getFloat("scale"),
				Color::fromARGB($data->getInt("mapWaterColorARGB") & 0xffffffff),
				$data->getByte("rain") !== 0,
				$tags,
			);
		}

		return $entries;
	}

	/**
	 * Loads the definitions of the blocks the game drives from data rather than implementing natively,
	 * from a bedrock-network-data block_definitions.nbt. A client that never receives one of these
	 * falls back to the raw identifier, draws no icon and refuses to place the block.
	 *
	 * @return list<BlockPaletteEntry>
	 */
	private static function loadBlockDefinitionsFromFile(string $filePath) : array{
		$blocks = self::loadCompoundFromFile($filePath)->getListTag("blocks") ??
			throw new SavedDataLoadingException("$filePath missing blocks");

		$definitions = [];
		foreach($blocks as $blockTag){
			if(!($blockTag instanceof CompoundTag)){
				throw new SavedDataLoadingException("blocks should only contain compounds");
			}
			$definitions[] = new BlockPaletteEntry(
				$blockTag->getString("name"),
				new CacheableNbt($blockTag->getCompoundTag("properties") ??
					throw new SavedDataLoadingException("Block definition is missing properties"))
			);
		}

		return $definitions;
	}

	/**
	 * The client keeps its own copy of the vanilla shapes, but it only finds one by the name the server
	 * gives it, so the names have to travel with the shapes. A shape without a name is still sent,
	 * because the ones that have a name are found by their position in the list.
	 *
	 * @return array{list<SerializableVoxelShape>, array<string, int>}
	 */
	private static function loadVoxelShapesFromFile(string $filePath) : array{
		$data = json_decode(Filesystem::fileGetContents($filePath), true);

		if(!is_array($data) || !array_is_list($data)){
			throw new SavedDataLoadingException($filePath . " should contain vanilla voxel shape list");
		}

		$shapes = [];
		$nameMap = [];

		foreach($data as $shape){
			if(!is_array($shape)){
				throw new SavedDataLoadingException($filePath . " contains an invalid voxel shape");
			}

			$identifier = $shape["identifier"] ?? null;
			if(is_string($identifier)){
				$nameMap[$identifier] = count($shapes);
			}

			$boxes = $shape["boxes"] ?? [];

			if(!is_array($boxes) || !array_is_list($boxes)){
				throw new SavedDataLoadingException($filePath . " contains an invalid voxel shape boxes list");
			}

			if($boxes === []){
				//an empty shape still carries one cutting plane per axis, the same as the game sends
				$voxelShape = new SerializableVoxelShape(
					new SerializableVoxelCells(0, 0, 0, []),
					[0.0],
					[0.0],
					[0.0]
				);
			}else{
				$xCoordinates = [];
				$yCoordinates = [];
				$zCoordinates = [];

				foreach($boxes as $box){
					if(
						!is_array($box) ||
						count($box) !== 2 ||
						!isset($box[0], $box[1]) ||
						!is_array($box[0]) ||
						!is_array($box[1]) ||
						count($box[0]) !== 3 ||
						count($box[1]) !== 3
					){
						throw new SavedDataLoadingException($filePath . " contains an invalid voxel box");
					}

					$min = $box[0];
					$max = $box[1];

					if(
						!is_int($min[0]) || !is_int($min[1]) || !is_int($min[2]) ||
						!is_int($max[0]) || !is_int($max[1]) || !is_int($max[2])
					){
						throw new SavedDataLoadingException($filePath . " contains an invalid voxel box coordinate");
					}

					$xCoordinates[] = $min[0] / 16.0;
					$xCoordinates[] = $max[0] / 16.0;
					$yCoordinates[] = $min[1] / 16.0;
					$yCoordinates[] = $max[1] / 16.0;
					$zCoordinates[] = $min[2] / 16.0;
					$zCoordinates[] = $max[2] / 16.0;
				}

				$xCoordinates = array_values(array_unique($xCoordinates, SORT_REGULAR));
				$yCoordinates = array_values(array_unique($yCoordinates, SORT_REGULAR));
				$zCoordinates = array_values(array_unique($zCoordinates, SORT_REGULAR));

				sort($xCoordinates, SORT_NUMERIC);
				sort($yCoordinates, SORT_NUMERIC);
				sort($zCoordinates, SORT_NUMERIC);

				$resX = count($xCoordinates) - 1;
				$resY = count($yCoordinates) - 1;
				$resZ = count($zCoordinates) - 1;

				$storage = [];

				for($i = 0, $storageSize = intdiv($resX * $resY * $resZ + 7, 8); $i < $storageSize; ++$i){
					$storage[] = 0;
				}

				for($z = 0; $z < $resZ; ++$z){
					for($y = 0; $y < $resY; ++$y){
						for($x = 0; $x < $resX; ++$x){
							$midX = ($xCoordinates[$x] + $xCoordinates[$x + 1]) / 2.0;
							$midY = ($yCoordinates[$y] + $yCoordinates[$y + 1]) / 2.0;
							$midZ = ($zCoordinates[$z] + $zCoordinates[$z + 1]) / 2.0;

							foreach($boxes as $box){
								$min = $box[0];
								$max = $box[1];

								if(
									$midX >= $min[0] / 16.0 && $midX <= $max[0] / 16.0 &&
									$midY >= $min[1] / 16.0 && $midY <= $max[1] / 16.0 &&
									$midZ >= $min[2] / 16.0 && $midZ <= $max[2] / 16.0
								){
									$index = $z + ($y * $resZ) + ($x * $resZ * $resY);
									$storage[intdiv($index, 8)] |= 1 << ($index % 8);
									break;
								}
							}
						}
					}
				}

				/** @var list<int> $storage */
				$storage = array_values($storage);

				$voxelShape = new SerializableVoxelShape(
					new SerializableVoxelCells($resX, $resY, $resZ, $storage),
					$xCoordinates,
					$yCoordinates,
					$zCoordinates
				);
			}

			$shapes[] = $voxelShape;
		}

		return [$shapes, $nameMap];
	}

	private static function make() : self{
		[$voxelShapes, $voxelShapeNames] = self::loadVoxelShapesFromFile(BedrockDataFiles::VOXEL_SHAPES_JSON);

		return new self(
			BiomeDefinitionListPacket::fromDefinitions(self::loadBiomeDefinitionModel(BedrockDataFiles::BIOME_DEFINITIONS_NBT)),
			AvailableActorIdentifiersPacket::create(new CacheableNbt(self::loadCompoundFromFile(BedrockDataFiles::ENTITY_IDENTIFIERS_NBT))),
			VoxelShapesPacket::create($voxelShapes, $voxelShapeNames, 0), //no custom shapes, only the vanilla ones
			self::loadBlockDefinitionsFromFile(BedrockDataFiles::BLOCK_DEFINITIONS_NBT)
		);
	}

	/**
	 * @param BlockPaletteEntry[] $blockDefinitions
	 * @phpstan-param list<BlockPaletteEntry> $blockDefinitions
	 */
	public function __construct(
		private BiomeDefinitionListPacket $biomeDefinitionList,
		private AvailableActorIdentifiersPacket $availableActorIdentifiers,
		private VoxelShapesPacket $voxelShapesPacket,
		private array $blockDefinitions
	){}

	public function getBiomeDefinitionList() : BiomeDefinitionListPacket{
		return $this->biomeDefinitionList;
	}

	public function getAvailableActorIdentifiers() : AvailableActorIdentifiersPacket{
		return $this->availableActorIdentifiers;
	}

	public function getVoxelShapes() : VoxelShapesPacket {
		return $this->voxelShapesPacket;
	}

	/**
	 * @return list<BlockPaletteEntry>
	 */
	public function getBlockDefinitions() : array{
		return $this->blockDefinitions;
	}
}
