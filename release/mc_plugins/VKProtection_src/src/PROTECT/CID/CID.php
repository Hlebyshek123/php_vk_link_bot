<?php
namespace PROTECT\CID;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as CLR;

use pocketmine\IPlayer;
use pocketmine\Player;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;

class CID extends PluginBase implements Listener {
	
	const TYPE_SKIN = 0;
	const TYPE_CID = 1;
	const TYPE_UUID = 2;
	
	const TYPE_ALL = 3;
	
	const RESPONSE_OK = 0;
	const RESPONSE_FAIL = 1;
	const RESPONSE_ERROR = 2;
	
	/** @var MessageController */
	private $messages;
	
	/** @var SwitcherController */
	private $switchers;
	
	/** @var DatabaseController */
	private $database;
	
	/** @var PermissionController */
	private $permissions;
	
	/** @var MPCommand */
	private $command;
	
	public function onEnable() {
		@mkdir($this->getDataFolder());
		@mkdir($this->getDataFolder() . 'config');
		@mkdir($this->getDataFolder() . 'data');
		file_put_contents($this->getDataFolder() . ($file = 'info.txt'), $this->getResource($file));
		
		$this->messages = new MessageController($this);
		$this->switchers = new SwitcherController($this);
		$this->database = new DatabaseController($this);
		$this->permissions = new PermissionController($this);
		
		$this->command = new MPCommand($this);
		$this->getServer()->getCommandMap()->register($this->getDescription()->getName(), $this->command);
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info("\n§e===================\n§fVK§cProtection §aВключен\n§fЯ тебя защищу малыха!\n§e===================");
	}
	
	public function enableProtection($player, int $protectionType, $data = null) : int {
		$isPlayer = false;
		if ($player instanceof IPlayer) {
			$playerName = $player->getName();
			if (!($isPlayer = ($player instanceof Player)) && $data === null) {
				return self::RESPONSE_ERROR;
			}
		} else {
			$playerName = $player;
			if ($data === null) {
				return self::RESPONSE_ERROR;
			}
		}
		
		switch ($protectionType) {
			case self::TYPE_SKIN:
				$data = ($isPlayer ? $player->getSkinData() : $data);
				$this->getDatabase()->addSkinProtection($playerName, $data);
				break;
			case self::TYPE_CID:
				$data = ($isPlayer ? $player->getClientId() : $data);
				$this->getDatabase()->addCidProtection($playerName, $data);
				break;
			case self::TYPE_UUID:
				$data = ($isPlayer ? $player->getRawUniqueId() : $data);
				$this->getDatabase()->addUuidProtection($playerName, $data);
				break;
			default:
				return self::RESPONSE_ERROR;
		}
		
		return self::RESPONSE_OK;
	}
	
	public function checkProtection($player, int $protectionType = self::TYPE_ALL, $data = null) : array {
		$isPlayer = false;
		if ($player instanceof IPlayer) {
			$playerName = $player->getName();
			if (!($isPlayer = ($player instanceof Player)) && $data === null) {
				return [self::RESPONSE_ERROR, null];
			}
		} else {
			$playerName = $player;
			if ($data === null) {
				return [self::RESPONSE_ERROR, null];
			}
		}
		
		switch ($protectionType) {
			case self::TYPE_ALL:
				if ($isPlayer) {
					$skin = $player->getSkinData();
					$cid = $player->getClientId();
					$uuid = $player->getRawUniqueId();
				} else {
					if (is_array($data) && count($data) === 3) {
						list($skin, $cid, $uuid) = array_values($data);
					} else {
						return [self::RESPONSE_ERROR, null];
					}
				}
				$db = $this->getDatabase();
				$sw = $this->getSwitchers();
				return (!$sw->isSkinEnabled() || $db->checkSkinProtection($playerName, $skin) ? (!$sw->isCidEnabled() || $db->checkCidProtection($playerName, $cid) ? (!$sw->isUuidEnabled() || $db->checkUuidProtection($playerName, $uuid) ? [self::RESPONSE_OK, null] : [self::RESPONSE_FAIL, self::TYPE_UUID]) : [self::RESPONSE_FAIL, self::TYPE_CID]) : [self::RESPONSE_FAIL, self::TYPE_SKIN]);
			case self::TYPE_SKIN:
				$data = ($isPlayer ? $player->getSkinData() : $data);
				return (!$sw->isSkinEnabled() || $this->getDatabase()->checkSkinProtection($playerName, $data) ? [self::RESPONSE_OK, null] : [self::RESPONSE_FAIL, $protectionType]);
			case self::TYPE_CID:
				$data = ($isPlayer ? $player->getClientId() : $data);
				return (!$sw->isCidEnabled() || $this->getDatabase()->checkCidProtection($playerName, $data) ? [self::RESPONSE_OK, null] : [self::RESPONSE_FAIL, $protectionType]);
			case self::TYPE_UUID:
				$data = ($isPlayer ? $player->getRawUniqueId() : $data);
				return (!$sw->isUuidEnabled() || $this->getDatabase()->checkUuidProtection($playerName, $data) ? [self::RESPONSE_OK, null] : [self::RESPONSE_FAIL, $protectionType]);
			default:
				return [self::RESPONSE_ERROR, null];
		}
	}
	
	public function disableProtection($player, int $protectionType) : int {
		if ($player instanceof IPlayer) {
			$playerName = $player->getName();
		} else {
			$playerName = $player;
		}
		
		switch ($protectionType) {
			case self::TYPE_SKIN:
				$this->getDatabase()->removeSkinProtection($playerName);
				break;
			case self::TYPE_CID:
				$this->getDatabase()->removeCidProtection($playerName);
				break;
			case self::TYPE_UUID:
				$this->getDatabase()->removeUuidProtection($playerName);
				break;
			default:
				return self::RESPONSE_ERROR;
		}
		
		return self::RESPONSE_OK;
	}
	
	public function onLogin(PlayerPreLoginEvent $e) {
		list($status, $type) = $this->checkProtection($player = $e->getPlayer());
		if ($status === self::RESPONSE_FAIL) {
			$msg = $this->getMessages()->getKickMessageByType($type, [$player->getName()]);
			$e->setCancelled(true);
			$e->setKickMessage($msg);
		}
	}
	
	public function getMessages() {
		return $this->messages;
	}
	
	public function getSwitchers() {
		return $this->switchers;
	}
	
	public function getDatabase() {
		return $this->database;
	}
	
	public function getPermissions() {
		return $this->permissions;
	}
	
	public function getCommand($name = null) {
		return $this->command;
	}

    public function onDisable(): void {
    if ($this->database !== null) {
        $this->database->close();
    }
}
	
}
