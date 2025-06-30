<?php
namespace PROTECT\CID;

class MessageController {
	
	/** @var string */
	private $usage_message;
	
	/** @var string[] */
	private $skin_messages = [];
	
	/** @var string[] */
	private $cid_messages = [];
	
	/** @var string[] */
	private $uuid_messages = [];
	
	public function __construct(CID $plugin) {
		$defaultConfig = stream_get_contents($plugin->getResource($file = 'messages.yml'));
		$data = $defaultData = yaml_parse($defaultConfig);
		
		if (!file_exists($path = $plugin->getDataFolder() . 'config/' . $file))
			file_put_contents($path, $defaultConfig);
		else
			$data = yaml_parse(file_get_contents($path));
		
		$allData = array_replace_recursive($defaultData, $data);
		
		$this->usage_message = $allData['usage-message'];
		$this->skin_messages = $allData['skin-messages'];
		$this->cid_messages = $allData['cid-messages'];
		$this->uuid_messages = $allData['uuid-messages'];
	}
	
	public function getUsageMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%', '%SKIN-USAGE%', '%CID-USAGE%', '%UUID-USAGE%'], $data, $this->usage_message) : $this->usage_message);
	}
	
	public function getSkinEnableMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->skin_messages['enable-msg']) : $this->skin_messages['enable-msg']);
	}
	
	public function getSkinKickMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->skin_messages['kick-msg']) : $this->skin_messages['kick-msg']);
	}
	
	public function getSkinDisableMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->skin_messages['disable-msg']) : $this->skin_messages['disable-msg']);
	}
	
	public function getSkinRestoreMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->skin_messages['restore-msg']) : $this->skin_messages['restore-msg']);
	}
	
	public function getSkinNoPermSwitchMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->skin_messages['noperm-switch-msg']) : $this->skin_messages['noperm-switch-msg']);
	}
	
	public function getSkinNoPermRestoreMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->skin_messages['noperm-restore-msg']) : $this->skin_messages['noperm-restore-msg']);
	}
	
	public function getCidEnableMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->cid_messages['enable-msg']) : $this->cid_messages['enable-msg']);
	}
	
	public function getCidKickMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->cid_messages['kick-msg']) : $this->cid_messages['kick-msg']);
	}
	
	public function getCidDisableMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->cid_messages['disable-msg']) : $this->cid_messages['disable-msg']);
	}
	
	public function getCidRestoreMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->cid_messages['restore-msg']) : $this->cid_messages['restore-msg']);
	}
	
	public function getCidNoPermSwitchMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->cid_messages['noperm-switch-msg']) : $this->cid_messages['noperm-switch-msg']);
	}
	
	public function getCidNoPermRestoreMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->cid_messages['noperm-restore-msg']) : $this->cid_messages['noperm-restore-msg']);
	}
	
	public function getUuidEnableMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->uuid_messages['enable-msg']) : $this->uuid_messages['enable-msg']);
	}
	
	public function getUuidKickMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->uuid_messages['kick-msg']) : $this->uuid_messages['kick-msg']);
	}
	
	public function getUuidDisableMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->uuid_messages['disable-msg']) : $this->uuid_messages['disable-msg']);
	}
	
	public function getUuidRestoreMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->uuid_messages['restore-msg']) : $this->uuid_messages['restore-msg']);
	}
	
	public function getUuidNoPermSwitchMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->uuid_messages['noperm-switch-msg']) : $this->uuid_messages['noperm-switch-msg']);
	}
	
	public function getUuidNoPermRestoreMessage($data = null) {
		return (is_array($data) ? str_replace(['%NICK%'], $data, $this->uuid_messages['noperm-restore-msg']) : $this->uuid_messages['noperm-restore-msg']);
	}
	
	public function getEnableMessageByType(int $type, $data = null) {
		switch ($type) {
			case CID::TYPE_SKIN:
				return $this->getSkinEnableMessage($data);
			case CID::TYPE_CID:
				return $this->getCidEnableMessage($data);
			case CID::TYPE_UUID:
				return $this->getUuidEnableMessage($data);
			default:
				return false;
		}
	}
	
	public function getKickMessageByType(int $type, $data = null) {
		switch ($type) {
			case CID::TYPE_SKIN:
				return $this->getSkinKickMessage($data);
			case CID::TYPE_CID:
				return $this->getCidKickMessage($data);
			case CID::TYPE_UUID:
				return $this->getUuidKickMessage($data);
			default:
				return false;
		}
	}
	
	public function getDisableMessageByType(int $type, $data = null) {
		switch ($type) {
			case CID::TYPE_SKIN:
				return $this->getSkinDisableMessage($data);
			case CID::TYPE_CID:
				return $this->getCidDisableMessage($data);
			case CID::TYPE_UUID:
				return $this->getUuidDisableMessage($data);
			default:
				return false;
		}
	}
	
	public function getRestoreMessageByType(int $type, $data = null) {
		switch ($type) {
			case CID::TYPE_SKIN:
				return $this->getSkinRestoreMessage($data);
			case CID::TYPE_CID:
				return $this->getCidRestoreMessage($data);
			case CID::TYPE_UUID:
				return $this->getUuidRestoreMessage($data);
			default:
				return false;
		}
	}
	
	public function getNoPermSwitchMessageByType(int $type, $data = null) {
		switch ($type) {
			case CID::TYPE_SKIN:
				return $this->getSkinNoPermSwitchMessage($data);
			case CID::TYPE_CID:
				return $this->getCidNoPermSwitchMessage($data);
			case CID::TYPE_UUID:
				return $this->getUuidNoPermSwitchMessage($data);
			default:
				return false;
		}
	}
	
	public function getNoPermRestoreMessageByType(int $type, $data = null) {
		switch ($type) {
			case CID::TYPE_SKIN:
				return $this->getSkinNoPermRestoreMessage($data);
			case CID::TYPE_CID:
				return $this->getCidNoPermRestoreMessage($data);
			case CID::TYPE_UUID:
				return $this->getUuidNoPermRestoreMessage($data);
			default:
				return false;
		}
	}
	
}
