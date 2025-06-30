<?php
namespace PROTECT\CID;

class SwitcherController {
	
	/** @var bool */
	private $skin_enabled;
	
	/** @var bool */
	private $cid_enabled;
	
	/** @var bool */
	private $uuid_enabled;
	
	public function __construct(CID $plugin) {
		$defaultConfig = stream_get_contents($plugin->getResource($file = 'switchers.yml'));
		$data = $defaultData = yaml_parse($defaultConfig);
		
		if (!file_exists($path = $plugin->getDataFolder() . 'config/' . $file))
			file_put_contents($path, $defaultConfig);
		else
			$data = yaml_parse(file_get_contents($path));
		
		$allData = array_replace_recursive($defaultData, $data);
		
		$this->skin_enabled = (bool) $allData['skin-enable'];
		$this->cid_enabled = (bool) $allData['cid-enable'];
		$this->uuid_enabled = (bool) $allData['uuid-enable'];
	}
	
	public function isSkinEnabled() : bool {
		return $this->skin_enabled;
	}
	
	public function isCidEnabled() : bool {
		return $this->cid_enabled;
	}
	
	public function isUuidEnabled() : bool {
		return $this->uuid_enabled;
	}
	
}
