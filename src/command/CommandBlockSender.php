<?php

declare(strict_types=1);

namespace pocketmine\command;

use pocketmine\lang\Language;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\PermissibleBase;
use pocketmine\permission\PermissibleDelegateTrait;
use pocketmine\Server;

class CommandBlockSender implements CommandSender{
	use PermissibleDelegateTrait;

	/** @phpstan-var positive-int|null */
	protected ?int $lineHeight = null;

	private string $lastOutput = "";

	public function __construct(
		private Server $server,
		private string $customName
	){
		$this->perm = new PermissibleBase([DefaultPermissions::ROOT_CONSOLE => true]);
	}

	public function getServer() : Server{
		return $this->server;
	}

	public function getLanguage() : Language{
		return $this->server->getLanguage();
	}

	public function sendMessage(Translatable|string $message) : void{
		if($message instanceof Translatable){
			$message = $this->getLanguage()->translate($message);
		}
		$this->lastOutput = $message;
	}

	public function getName() : string{
		return $this->customName;
	}

	public function getLastOutput() : string{
		return $this->lastOutput;
	}

	public function getScreenLineHeight() : int{
		return $this->lineHeight ?? PHP_INT_MAX;
	}

	public function setScreenLineHeight(?int $height) : void{
		if($height !== null && $height < 1){
			throw new \InvalidArgumentException("Line height must be at least 1");
		}
		$this->lineHeight = $height;
	}
}
