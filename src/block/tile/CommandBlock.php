<?php

declare(strict_types=1);

namespace pocketmine\block\tile;

use pocketmine\block\inventory\CommandBlockInventory;
use pocketmine\command\CommandBlockSender;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;

class CommandBlock extends Spawnable{

	public const TAG_CUSTOM_NAME = "CustomName";
	public const TAG_COMMAND = "Command";
	public const TAG_LAST_OUTPUT = "LastOutput";
	public const TAG_AUTO = "auto";
	public const TAG_TRACK_OUTPUT = "TrackOutput";
	public const TAG_CONDITION_MET = "conditionMet";

	private string $customName = "";
	private string $command = "";
	private string $lastOutput = "";
	private bool $auto = false;
	private bool $trackOutput = true;
	private bool $conditionMet = false;

	private CommandBlockInventory $inventory;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new CommandBlockInventory($this->position, 0);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->customName = $nbt->getString(self::TAG_CUSTOM_NAME, "");
		$this->command = $nbt->getString(self::TAG_COMMAND, "");
		$this->lastOutput = $nbt->getString(self::TAG_LAST_OUTPUT, "");
		$this->auto = $nbt->getByte(self::TAG_AUTO, 0) !== 0;
		$this->trackOutput = $nbt->getByte(self::TAG_TRACK_OUTPUT, 1) !== 0;
		$this->conditionMet = $nbt->getByte(self::TAG_CONDITION_MET, 0) !== 0;
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setString(self::TAG_CUSTOM_NAME, $this->customName);
		$nbt->setString(self::TAG_COMMAND, $this->command);
		$nbt->setString(self::TAG_LAST_OUTPUT, $this->lastOutput);
		$nbt->setByte(self::TAG_AUTO, (int) $this->auto);
		$nbt->setByte(self::TAG_TRACK_OUTPUT, (int) $this->trackOutput);
		$nbt->setByte(self::TAG_CONDITION_MET, (int) $this->conditionMet);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$nbt->setString(self::TAG_COMMAND, $this->command);
		$nbt->setString(self::TAG_LAST_OUTPUT, $this->lastOutput);
		$nbt->setByte(self::TAG_AUTO, (int) $this->auto);
		$nbt->setByte(self::TAG_TRACK_OUTPUT, (int) $this->trackOutput);
		$nbt->setByte(self::TAG_CONDITION_MET, (int) $this->conditionMet);
	}

	public function execute() : bool{
		if($this->command === "" || $this->closed){
			return false;
		}

		$world = $this->position->getWorld();
		$server = $world->getServer();
		$sender = new CommandBlockSender($server, empty($this->customName) ? "!" : $this->customName);

		$success = $server->dispatchCommand($sender, $this->command);

		if($this->trackOutput){
			$output = $sender->getLastOutput();
			if($output !== $this->lastOutput){
				$this->lastOutput = $output;
				$this->clearSpawnCompoundCache();
			}
		}

		$this->conditionMet = $success;
		return $success;
	}

	public function getInventory() : CommandBlockInventory{
		return $this->inventory;
	}

	public function getCustomName() : string{ return $this->customName; }

	public function setCustomName(string $customName) : void{
		$this->customName = $customName;
		$this->clearSpawnCompoundCache();
	}

	public function getCommand() : string{ return $this->command; }

	public function setCommand(string $command) : void{
		$this->command = $command;
		$this->clearSpawnCompoundCache();
	}

	public function getLastOutput() : string{ return $this->lastOutput; }

	public function setLastOutput(string $lastOutput) : void{
		$this->lastOutput = $lastOutput;
		$this->clearSpawnCompoundCache();
	}

	public function isAuto() : bool{ return $this->auto; }

	public function setAuto(bool $auto) : void{
		$this->auto = $auto;
		$this->clearSpawnCompoundCache();
	}

	public function isTrackOutput() : bool{ return $this->trackOutput; }

	public function setTrackOutput(bool $trackOutput) : void{
		$this->trackOutput = $trackOutput;
		$this->clearSpawnCompoundCache();
	}

	public function isConditionMet() : bool{ return $this->conditionMet; }

	public function setConditionMet(bool $conditionMet) : void{
		$this->conditionMet = $conditionMet;
		$this->clearSpawnCompoundCache();
	}
}
