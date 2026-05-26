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
use pocketmine\command\CommandEnum;
use pocketmine\command\CommandParameter;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

class GamemodeCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"gamemode",
			KnownTranslationFactory::pocketmine_command_gamemode_description(),
			KnownTranslationFactory::commands_gamemode_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_GAMEMODE_SELF,
			DefaultPermissionNames::COMMAND_GAMEMODE_OTHER,
		]);

		$this->setOverload("default",
			CommandParameter::int("gameMode"),
			CommandParameter::target("player", optional: true)
		);
		$this->setOverload("byString",
			CommandParameter::enum("gameMode", new CommandEnum("GameMode", ["survival", "creative", "adventure", "spectator"])),
			CommandParameter::target("player", optional: true)
		);
		$this->enableParamTree();
	}

	protected function onRun(CommandSender $sender, string $aliasUsed, array $args, string $overload = "default") : void{
		$gameModeStr = (string) ($args["gameMode"] ?? "");
		$gameMode = GameMode::fromString($gameModeStr);
		if($gameMode === null){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gamemode_unknown($gameModeStr));
			return;
		}

		if(isset($args["player"])){
			$target = $args["player"];
			if(!$target instanceof Player){
				return;
			}
			if(!$this->testPermission($sender, DefaultPermissionNames::COMMAND_GAMEMODE_OTHER)){
				return;
			}
		}else{
			if(!$sender instanceof Player){
				throw new InvalidCommandSyntaxException();
			}
			$target = $sender;
			if(!$this->testPermission($sender, DefaultPermissionNames::COMMAND_GAMEMODE_SELF)){
				return;
			}
		}

		if($target->getGamemode() === $gameMode){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gamemode_failure($target->getName()));
			return;
		}

		$target->setGamemode($gameMode);
		if($gameMode !== $target->getGamemode()){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gamemode_failure($target->getName()));
		}elseif($target === $sender){
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_gamemode_success_self($gameMode->getTranslatableName()));
		}else{
			$target->sendMessage(KnownTranslationFactory::gameMode_changed($gameMode->getTranslatableName()));
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_gamemode_success_other($gameMode->getTranslatableName(), $target->getName()));
		}
	}
}
