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

use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use function array_key_last;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function ksort;
use function strtolower;

abstract class OverloadedCommand extends Command{

	/** @var SubCommand[] name => subcommand */
	private array $subCommands = [];

	/** @var SubCommand[] alias => subcommand */
	private array $subCommandAliases = [];

	/**
	 * Named overloads: each key is an overload name, value is its parameter list.
	 * Populated via setOverload() or registerParameter() (which uses "default").
	 *
	 * @var array<string, list<CommandParameter>>
	 */
	protected array $commandParameters = [];

	private bool $paramTreeEnabled = false;

	/**
	 * Register a named overload (PNX-style).
	 * All parameters must be provided at once — they replace any existing overload with the same name.
	 */
	public function setOverload(string $name, CommandParameter ...$parameters) : void{
		$this->commandParameters[$name] = array_values($parameters);
	}

	/**
	 * Add a single parameter to the "default" overload, positionally ordered.
	 * Validates ordering constraints (no required after optional, no param after TEXT).
	 */
	public function registerParameter(int $position, CommandParameter $parameter) : void{
		if($position < 0){
			throw new \InvalidArgumentException("Parameter position must be >= 0");
		}
		$current = $this->commandParameters["default"] ?? [];
		if(count($current) > 0){
			$last = $current[array_key_last($current)];
			if($last->type === CommandParamType::TEXT){
				throw new \InvalidArgumentException("Cannot register parameter after TEXT parameter");
			}
			if($last->optional && !$parameter->optional){
				throw new \InvalidArgumentException("Required parameter cannot follow optional parameter");
			}
		}
		$current[$position] = $parameter;
		ksort($current);
		$this->commandParameters["default"] = array_values($current);
	}

	public function registerSubCommand(SubCommand $subCommand) : void{
		$name = strtolower($subCommand->getName());
		$this->subCommands[$name] = $subCommand;
		foreach($subCommand->getAliases() as $alias){
			$this->subCommandAliases[strtolower($alias)] = $subCommand;
		}
	}

	/**
	 * Enable automatic overload matching in execute().
	 * When enabled, execute() tries each overload in commandParameters order
	 * and calls onRun() with the matched overload name.
	 */
	public function enableParamTree() : void{
		$this->paramTreeEnabled = true;
	}

	/**
	 * @return SubCommand[]
	 */
	public function getSubCommands() : array{
		return $this->subCommands;
	}

	/**
	 * @return array<string, list<CommandParameter>>
	 */
	public function getCommandParameters() : array{
		return $this->commandParameters;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return null;
		}

		if(isset($args[0])){
			$subName = strtolower($args[0]);
			$sub = $this->subCommands[$subName] ?? $this->subCommandAliases[$subName] ?? null;
			if($sub !== null){
				if(!$sub->testPermission($sender)){
					return null;
				}
				$subArgs = array_slice($args, 1);
				$parsed = $sub->parseParameters($subArgs, $sender);
				if($parsed === null){
					$sender->sendMessage(TextFormat::RED . "Usage: " . $sub->generateUsageMessage($commandLabel));
					return null;
				}
				$sub->onRun($sender, $commandLabel, $parsed);
				return null;
			}
		}

		if($this->paramTreeEnabled){
			foreach(Utils::stringifyKeys($this->commandParameters) as $overloadName => $parameters){
				$parsed = $this->tryParseOverload($parameters, $args, $sender);
				if($parsed !== null){
					$this->onRun($sender, $commandLabel, $parsed, $overloadName);
					return null;
				}
			}
			$this->sendUsageMessage($sender, $commandLabel);
			return null;
		}

		$defaultParams = $this->commandParameters["default"] ?? [];
		$parsed = $this->tryParseOverload($defaultParams, $args, $sender);
		if($parsed === null){
			$this->sendUsageMessage($sender, $commandLabel);
			return null;
		}
		$this->onRun($sender, $commandLabel, $parsed, "default");
		return null;
	}

	/**
	 * Override to handle execution.
	 * $overload is the name of the matched overload from commandParameters.
	 *
	 * @param array<string, mixed> $args parsed parameter values keyed by parameter name
	 */
	protected function onRun(CommandSender $sender, string $aliasUsed, array $args, string $overload = "default") : void{
		$this->sendUsageMessage($sender, $aliasUsed);
	}

	protected function sendUsageMessage(CommandSender $sender, string $commandLabel) : void{
		if(count($this->subCommands) > 0){
			foreach($this->subCommands as $sub){
				if($sub->testPermissionSilent($sender)){
					$sender->sendMessage(
						TextFormat::YELLOW . $sub->generateUsageMessage($commandLabel) .
						TextFormat::WHITE . " - " . $sub->getDescription()
					);
				}
			}
			return;
		}
		foreach(Utils::stringifyKeys($this->commandParameters) as $overloadName => $parameters){
			$parts = ["/{$commandLabel}"];
			foreach($parameters as $param){
				$parts[] = $param->getUsageText();
			}
			$sender->sendMessage(TextFormat::YELLOW . implode(" ", $parts));
		}
	}

	/**
	 * @param list<CommandParameter> $parameters
	 * @param string[] $rawArgs
	 * @phpstan-param list<string> $rawArgs
	 * @return array<string, mixed>|null null if this overload does not match
	 */
	private function tryParseOverload(array $parameters, array $rawArgs, CommandSender $sender) : ?array{
		if(count($parameters) === 0){
			return count($rawArgs) === 0 ? [] : null;
		}
		$parsed = [];
		foreach($parameters as $i => $param){
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
}
