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

use pocketmine\permission\PermissionManager;
use pocketmine\utils\TextFormat;
use function array_key_last;
use function array_slice;
use function count;
use function explode;
use function implode;
use function ksort;
use const PHP_INT_MAX;

abstract class SubCommand{

	/**
	 * @var CommandParameter[]
	 * @phpstan-var array<int, CommandParameter>
	 */
	private array $parameters = [];

	/** @var string[] */
	private array $permissions = [];

	/**
	 * @param string[] $aliases
	 * @phpstan-param list<string> $aliases
	 */
	public function __construct(
		private string $name,
		private string $description = "",
		private array $aliases = []
	){}

	public function getName() : string{
		return $this->name;
	}

	public function getDescription() : string{
		return $this->description;
	}

	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function getAliases() : array{
		return $this->aliases;
	}

	/**
	 * @return string[]
	 */
	public function getPermissions() : array{
		return $this->permissions;
	}

	public function setPermission(string $permission) : void{
		$perms = explode(";", $permission);
		$manager = PermissionManager::getInstance();
		foreach($perms as $perm){
			if($manager->getPermission($perm) === null){
				throw new \InvalidArgumentException("Cannot use non-existing permission \"{$perm}\"");
			}
		}
		$this->permissions = $perms;
	}

	public function testPermission(CommandSender $sender) : bool{
		if($this->testPermissionSilent($sender)){
			return true;
		}
		$sender->sendMessage(TextFormat::RED . "You don't have permission to use this subcommand.");
		return false;
	}

	public function testPermissionSilent(CommandSender $sender) : bool{
		if(count($this->permissions) === 0){
			return true;
		}
		foreach($this->permissions as $perm){
			if($sender->hasPermission($perm)){
				return true;
			}
		}
		return false;
	}

	public function registerParameter(int $position, CommandParameter $parameter) : void{
		if($position < 0){
			throw new \InvalidArgumentException("Parameter position must be >= 0");
		}
		if(count($this->parameters) > 0){
			$last = $this->parameters[array_key_last($this->parameters)];
			if($last->type === CommandParamType::TEXT){
				throw new \InvalidArgumentException("Cannot register parameter after TEXT parameter");
			}
			if($last->optional && !$parameter->optional){
				throw new \InvalidArgumentException("Required parameter cannot follow optional parameter");
			}
		}
		$this->parameters[$position] = $parameter;
		ksort($this->parameters);
	}

	/**
	 * @return CommandParameter[]
	 * @phpstan-return array<int, CommandParameter>
	 */
	public function getParameters() : array{
		return $this->parameters;
	}

	public function generateUsageMessage(string $parentName) : string{
		$parts = ["/{$parentName}", $this->name];
		foreach($this->parameters as $param){
			$parts[] = $param->getUsageText();
		}
		return implode(" ", $parts);
	}

	/**
	 * @param string[] $rawArgs
	 * @phpstan-param list<string> $rawArgs
	 * @return array<string, mixed>|null null on parse failure
	 */
	public function parseParameters(array $rawArgs, CommandSender $sender) : ?array{
		$parsed = [];
		foreach($this->parameters as $i => $param){
			if(!isset($rawArgs[$i])){
				if(!$param->optional){
					return null;
				}
				break;
			}
			if($param->type === CommandParamType::TEXT){
				$text = implode(" ", array_slice($rawArgs, $i));
				if(!$param->validate($text, $sender)){
					return null;
				}
				$parsed[$param->name] = $param->parse($text, $sender);
				break;
			}
			if(!$param->validate($rawArgs[$i], $sender)){
				return null;
			}
			$parsed[$param->name] = $param->parse($rawArgs[$i], $sender);
		}
		return $parsed;
	}

	/**
	 * @param array<string, mixed> $args parsed parameter values keyed by parameter name
	 */
	abstract public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void;
}
