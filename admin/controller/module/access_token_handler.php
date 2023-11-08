<?php

namespace Opencart\Admin\Controller\Extension\Oblio\Module;

class AccessTokenHandler implements OblioApiAccessTokenHandlerInterface {
    private $_key = 'oblio_api_access_token';
    private $_module;
    
    public function __construct($module) {
        $this->_module = $module;
    }
    
    public function get() {
        $accessTokenJson = $this->_module->config->get($this->_key);
        if ($accessTokenJson) {
            $accessToken = json_decode($accessTokenJson);
            if ($accessToken && $accessToken->request_time + $accessToken->expires_in > time()) {
                return $accessToken;
            }
        }
        return false;
    }
    
    public function set($accessToken) {
        if (!is_string($accessToken)) {
            $accessToken = json_encode($accessToken);
        }
        $this->editSetting('oblio', [$this->_key => $accessToken]);
    }
    
    public function clear() {
        $this->editSetting('oblio', [$this->_key => '']);
    }

	public function editSetting(string $code, array $data, int $store_id = 0): void {
		$this->_module->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '" . (int)$store_id . "' AND `code` = '" . $this->_module->db->escape($code) . "'");

		foreach ($data as $key => $value) {
			if (substr($key, 0, strlen($code)) == $code) {
				if (!is_array($value)) {
					$this->_module->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '" . (int)$store_id . "', `code` = '" . $this->_module->db->escape($code) . "', `key` = '" . $this->_module->db->escape($key) . "', `value` = '" . $this->_module->db->escape($value) . "'");
				} else {
					$this->_module->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '" . (int)$store_id . "', `code` = '" . $this->_module->db->escape($code) . "', `key` = '" . $this->_module->db->escape($key) . "', `value` = '" . $this->_module->db->escape(json_encode($value)) . "', `serialized` = '1'");
				}
			}
		}
	}
}