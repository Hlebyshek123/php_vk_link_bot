<?php
namespace PROTECT\CID;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;

use pocketmine\event\TranslationContainer;

use pocketmine\Player;

class MPCommand extends Command implements PluginIdentifiableCommand {
	
	/** @var CID */
	private $plugin;
	
	/** @var string[] */
	private $skin_commands = [];
	
	/** @var string[] */
	private $cid_commands = [];
	
	/** @var string[] */
	private $uuid_commands = [];
	
	public function __construct(CID $plugin) {
		$this->plugin = $plugin;
		
		$defaultConfig = stream_get_contents($plugin->getResource($file = 'commands.yml'));
		$data = $defaultData = yaml_parse($defaultConfig);
		
		if (!file_exists($path = $plugin->getDataFolder() . 'config/' . $file))
			file_put_contents($path, $defaultConfig);
		else
			$data = yaml_parse(file_get_contents($path));
		
		$allData = array_replace_recursive($defaultData, $data);
		
		$name = $allData['main-command'];
		
		$switcher = $plugin->getSwitchers();
		$skin = $cid = $uuid = [];
		if ($switcher->isSkinEnabled())
			$skin = $allData['skin-commands'];
		if ($switcher->isCidEnabled())
			$cid = $allData['cid-commands'];
		if ($switcher->isUuidEnabled())
			$uuid = $allData['uuid-commands'];
		
		$this->skin_commands = $skin;
		$this->cid_commands = $cid;
		$this->uuid_commands = $uuid;
		
		parent::__construct($name, $plugin->getDescription()->getDescription());
		$this->setPermission($plugin->getPermissions()->getMainPermission());
	}
	
	public function execute(CommandSender $sender, $label, array $args) {
		$isPlayer = ($sender instanceof Player);
		$cmd = array_shift($args);
		
		if (in_array($cmd, $cmds = $this->skin_commands)) {
			$type = CID::TYPE_SKIN;
		} elseif (in_array($cmd, $cmds = $this->cid_commands)) {
			$type = CID::TYPE_CID;
		} elseif (in_array($cmd, $cmds = $this->uuid_commands)) {
			$type = CID::TYPE_UUID;
		} else {
			$name = $this->getName();
			$skin_usage = '/' . $name .  ' <' . implode('/', $this->skin_commands) . '>';
			$cid_usage = '/' . $name .  ' <' . implode('/', $this->cid_commands) . '>';
			$uuid_usage = '/' . $name .  ' <' . implode('/', $this->uuid_commands) . '>';
			$sender->sendMessage($this->getPlugin()->getMessages()->getUsageMessage([$sender->getName(), $skin_usage, $cid_usage, $uuid_usage]));
			return false;
		}
		
		switch ($cmd) {
			case $cmds['enable-cmd']:
				if (!$isPlayer) {
					$sender->sendMessage('Используйте эту команду в игре!');
					return false;
				} elseif (!$sender->hasPermission($this->getPlugin()->getPermissions()->getSwitchPermByType($type))) {
					$sender->sendMessage($this->getPlugin()->getMessages()->getNoPermSwitchMessageByType($type, [$sender->getName()]));
					return false;
				} else {
					$this->getPlugin()->enableProtection($sender, $type);
					$sender->sendMessage($this->getPlugin()->getMessages()->getEnableMessageByType($type, [$sender->getName()]));
					return true;
				}
			case $cmds['disable-cmd']:
				if (!$isPlayer) {
					$sender->sendMessage('Используйте эту команду в игре!');
					return false;
				} elseif (!$sender->hasPermission($this->getPlugin()->getPermissions()->getSwitchPermByType($type))) {
					$sender->sendMessage($this->getPlugin()->getMessages()->getNoPermSwitchMessageByType($type, [$sender->getName()]));
					return false;
				} else {
					$this->getPlugin()->disableProtection($sender, $type);
					$sender->sendMessage($this->getPlugin()->getMessages()->getDisableMessageByType($type, [$sender->getName()]));
					return true;
				}
			case $cmds['restore-cmd']:
				if (!$sender->hasPermission($this->getPlugin()->getPermissions()->getRestorePermByType($type))) {
					$sender->sendMessage($this->getPlugin()->getMessages()->getNoPermRestoreMessageByType($type, [$sender->getName()]));
					return false;
				} elseif (count($args) < 1 || empty($playerName = array_shift($args))) {
					$sender->sendMessage(new TranslationContainer('%commands.generic.usage', [$this->getUsage() . ' ' . $cmd . ' <ник>']));
					return false;
				} else {
					$this->getPlugin()->disableProtection($playerName, $type);
					$sender->sendMessage($this->getPlugin()->getMessages()->getRestoreMessageByType($type, [$playerName]));
					return true;
				}
		}
	}
	
	public function getPlugin() {
		return $this->plugin;
	}
	
}
