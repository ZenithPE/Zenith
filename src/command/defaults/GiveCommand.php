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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandParameter;
use pocketmine\command\CommandSender;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\item\StringToItemParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\nbt\JsonNbtParser;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\NbtException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class GiveCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"give",
			KnownTranslationFactory::pocketmine_command_give_description(),
			KnownTranslationFactory::pocketmine_command_give_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_GIVE_SELF,
			DefaultPermissionNames::COMMAND_GIVE_OTHER,
		]);

		$this->setOverload("default",
			CommandParameter::target("player"),
			CommandParameter::item("itemName"),
			CommandParameter::int("amount", optional: true, min: 1, max: 32767),
			CommandParameter::text("tags", optional: true)
		);
		$this->enableParamTree();
	}

	protected function onRun(CommandSender $sender, string $aliasUsed, array $args, string $overload = "default") : void{
		$player = $args["player"] ?? null;
		if(!$player instanceof Player){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerNotFound("?")->prefix(TextFormat::RED));
			return;
		}

		$permission = $player === $sender
			? DefaultPermissionNames::COMMAND_GIVE_SELF
			: DefaultPermissionNames::COMMAND_GIVE_OTHER;
		if(!$this->testPermission($sender, $permission)){
			return;
		}

		$itemName = (string) ($args["itemName"] ?? "");
		try{
			$item = StringToItemParser::getInstance()->parse($itemName) ?? LegacyStringToItemParser::getInstance()->parse($itemName);
		}catch(LegacyStringToItemParserException){
			$sender->sendMessage(KnownTranslationFactory::commands_give_item_notFound($itemName)->prefix(TextFormat::RED));
			return;
		}

		$item->setCount(isset($args["amount"]) ? (int) $args["amount"] : $item->getMaxStackSize());

		if(isset($args["tags"])){
			$rawTags = (string) $args["tags"];
			try{
				$tags = JsonNbtParser::parseJson($rawTags);
			}catch(NbtDataException $e){
				$sender->sendMessage(KnownTranslationFactory::commands_give_tagError($e->getMessage()));
				return;
			}
			try{
				$item->setNamedTag($tags);
			}catch(NbtException $e){
				$sender->sendMessage(KnownTranslationFactory::commands_give_tagError($e->getMessage()));
				return;
			}
		}

		//TODO: overflow
		$player->getInventory()->addItem($item);

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_give_success(
			$item->getName() . " (" . $itemName . ")",
			(string) $item->getCount(),
			$player->getName()
		));
	}
}
