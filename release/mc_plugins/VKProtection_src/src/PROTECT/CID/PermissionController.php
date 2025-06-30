<?php
namespace PROTECT\CID;

use pocketmine\permission\Permission;

class PermissionController {
	
	/** @var string */
	private $main_permission;
	
	/** @var string[] */
	private $skin_permissions = [];
	
	/** @var string[] */
	private $cid_permissions = [];
	
	/** @var string[] */
	private $uuid_permissions = [];
	
	public function __construct(CID $plugin) {
		$defaultConfig = stream_get_contents($plugin->getResource($file = 'permissions.yml'));
		$data = $defaultData = yaml_parse($defaultConfig);
		
		if (!file_exists($path = $plugin->getDataFolder() . 'config/' . $file))
			file_put_contents($path, $defaultConfig);
		else
			$data = yaml_parse(file_get_contents($path));
		
		$allData = array_replace_recursive($defaultData, $data);
		
		$this->main_permission = $allData['main-permission'];
		$this->skin_permissions = $allData['skin-permissions'];
		$this->cid_permissions = $allData['cid-permissions'];
		$this->uuid_permissions = $allData['uuid-permissions'];
		
		$pm = $plugin->getServer()->getPluginManager();
		
		$pm->addPermission(new Permission($this->main_permission, null, Permission::DEFAULT_TRUE));
		
		$pm->addPermission(new Permission($this->skin_permissions['switch-perm'], null, Permission::DEFAULT_TRUE));
		$pm->addPermission(new Permission($this->skin_permissions['restore-perm'], null, Permission::DEFAULT_OP));
		
		$pm->addPermission(new Permission($this->cid_permissions['switch-perm'], null, Permission::DEFAULT_TRUE));
		$pm->addPermission(new Permission($this->cid_permissions['restore-perm'], null, Permission::DEFAULT_OP));
		
		$pm->addPermission(new Permission($this->uuid_permissions['switch-perm'], null, Permission::DEFAULT_TRUE));
		$pm->addPermission(new Permission($this->uuid_permissions['restore-perm'], null, Permission::DEFAULT_OP));
	}
	
	public function getMainPermission() {
		return $this->main_permission;
	}
	
	public function getSkinSwitchPerm() {
		return $this->skin_permissions['switch-perm'];
	}
	
	public function getSkinRestorePerm() {
		return $this->skin_permissions['restore-perm'];
	}
	
	public function getCidSwitchPerm() {
		return $this->cid_permissions['switch-perm'];
	}
	
	public function getCidRestorePerm() {
		return $this->cid_permissions['restore-perm'];
	}
	
	public function getUuidSwitchPerm() {
		return $this->uuid_permissions['switch-perm'];
	}
	
	public function getUuidRestorePerm() {
		return $this->uuid_permissions['restore-perm'];
	}
	
	public function getSwitchPermByType(int $type) {
		switch ($type) {
			case CID::TYPE_SKIN:
				return $this->getSkinSwitchPerm();
			case CID::TYPE_CID:
				return $this->getCidSwitchPerm();
			case CID::TYPE_UUID:
				return $this->getUuidSwitchPerm();
			default:
				return false;
		}
	}
	
	public function getRestorePermByType(int $type) {
		switch ($type) {
			case CID::TYPE_SKIN:
				return $this->getSkinRestorePerm();
			case CID::TYPE_CID:
				return $this->getCidRestorePerm();
			case CID::TYPE_UUID:
				return $this->getUuidRestorePerm();
			default:
				return false;
		}
	}
	
}
