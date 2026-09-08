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

namespace pocketmine\block\utils;

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\math\Axis;
use pocketmine\math\Facing;

trait HorizontalConnectionsTrait{

	/** @var int[] facing => facing */
	protected array $connections = [];

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacingFlags($this->connections);
	}

	/** @return int[] */
	public function getConnections() : array{ return $this->connections; }

	public function isConnected(int $facing) : bool{
		return isset($this->connections[$facing]);
	}

	/** @return $this */
	public function setConnected(int $facing, bool $value) : self{
		self::validateHorizontal($facing);
		if($value){
			$this->connections[$facing] = $facing;
		}else{
			unset($this->connections[$facing]);
		}
		return $this;
	}

	/**
	 * @param int[] $connections
	 * @return $this
	 */
	public function setConnections(array $connections) : self{
		$uniqueConnections = [];
		foreach($connections as $facing){
			self::validateHorizontal($facing);
			$uniqueConnections[$facing] = $facing;
		}
		$this->connections = $uniqueConnections;
		return $this;
	}

	private static function validateHorizontal(int $facing) : void{
		$axis = Facing::axis($facing);
		if($axis !== Axis::X && $axis !== Axis::Z){
			throw new \InvalidArgumentException("Facing must be horizontal");
		}
	}

	/**
	 * Recalculates the connections to the block's horizontal neighbours.
	 * Returns whether anything changed.
	 */
	protected function recalculateConnections() : bool{
		$changed = false;
		foreach(Facing::HORIZONTAL as $facing){
			$connected = $this->canConnectTo($facing);
			if($connected !== isset($this->connections[$facing])){
				$this->setConnected($facing, $connected);
				$changed = true;
			}
		}

		if($changed){
			$this->collisionBoxes = null;
		}
		return $changed;
	}

	abstract protected function canConnectTo(int $facing) : bool;
}
