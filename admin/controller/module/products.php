<?php
namespace Opencart\Admin\Controller\Extension\Oblio\Module;

class Products {
    private $_module;
    private $_use_code;
    private $_languages;
    
    public function __construct($module) {
        $this->_module = $module;
        $this->_module->load->model('catalog/product');
        $this->_module->load->model('localisation/language');
        $this->_languages = $this->_module->model_localisation_language->getLanguages();
        
        $this->_module->load->model('setting/setting');
        $settings = $this->_module->model_setting_setting->getSetting('module_oblio');
        $this->_use_code = isset($settings['module_oblio_use_code']) ? $settings['module_oblio_use_code'] : 'model';
    }
    /**
     *  Finds product if it exists
     *  @param array data
     *  @return array product
     */
    public function find($data) {
        $product_id = 0;
        if (strlen($data['code']) > 0) {
            $sql = "SELECT product_id FROM " . DB_PREFIX . "product WHERE `{$this->_use_code}`='" . $this->_module->db->escape($data['code']) . "'";
            $query = $this->_module->db->query($sql);
            if ($query->row) {
                $product_id = (int) $query->row['product_id'];
            }
        }
        if (!$product_id) {
            foreach ($this->_languages as $language) {
                $sql = sprintf("SELECT product_id FROM " . DB_PREFIX . "product_description WHERE language_id=%d AND name='%s'",
                    $language['language_id'], $this->_module->db->escape($data['name']));
                $query = $this->_module->db->query($sql);
                if ($query->row) {
                    $product_id = (int) $query->row['product_id'];
                    break;
                }
            }
        }
        
        return $this->get($product_id);
    }
    
    /**
     *  Finds product by id
     *  @param int product_id
     *  @return array product
     */
    public function get($product_id) {
        return $this->_module->model_catalog_product->getProduct($product_id);
    }
    
    /**
     *  Insert product
     *  @param array data
     *  @return bool
     */
    public function insert($data) {
        if (empty($data['price'])) {
            return false;
        }
        $based = 'shipping';
        $isService = false;
        if (in_array(trim($data['productType']), ['-', 'Serviciu'])) {
            $based = 'payment';
            $isService = true;
        }
        
        $productData = [
            'name'                  => $data['name'],
            'model'                 => empty($data['code']) ? '' : $data['code'],
            'sku'                   => '',
            'upc'                   => '',
            'ean'                   => '',
            'jan'                   => '',
            'isbn'                  => '',
            'mpn'                   => '',
            'location'              => '',
            'stock_status_id'       => 0,
            'manufacturer_id'       => 0,
            'shipping'              => $isService ? 0 : 1,
            'points'                => 0,
            'date_available'        => date('Y-m-d'),
            'weight'                => 0,
            'weight_class_id'       => 1,
            'length'                => 0,
            'width'                 => 0,
            'height'                => 0,
            'length_class_id'       => 2,
            'subtract'              => $isService ? 0 : 1,
            'minimum'               => 1,
            'sort_order'            => 0,
            'status'                => 1,
            'viewed'                => 0,
            'date_added'            => date('Y-m-d H:i:s'),
            'date_modified'         => date('Y-m-d H:i:s'),
            'meta_title'            => $data['name'],
            'product_description'   => [],
            'product_store'         => [0], // default
            'price'                 => $this->getPrice($data),
            'tax_class_id'          => $this->getTaxClassId($data['vatPercentage']),
            'quantity'              => isset($data['quantity']) ? $data['quantity'] : 1,
        ];
        
        foreach ($this->_languages as $language) {
            $productData['product_description'][$language['language_id']] = [
                'name'              => $data['name'],
                'description'       => $data['description'],
                'tag'               => '',
                'meta_title'        => $data['name'],
                'meta_description'  => '',
                'meta_keyword'      => '',
            ];
        }
        
        $this->_module->model_catalog_product->addProduct($productData);
        return true;
    }
    
    /**
     *  Update product
     *  @param int product_id
     *  @param array data
     *  @return bool
     */
    public function update($product_id, $data) {
        if (empty($data['price'])) {
            return false;
        }
        if ($this->_module->getProductType($product_id) !== trim($data['productType'])) {
            return false;
        }
        $isService = false;
        if (in_array(trim($data['productType']), ['-', 'Serviciu'])) {
            $isService = true;
        }
        $model = empty($data['code']) ? '' : $data['code'];
        $quantity = $isService ? 100 : (int) $data['quantity'];
        $sql = "UPDATE " . DB_PREFIX . "product 
                SET
                    quantity = '" . $quantity . "',
                    date_modified = NOW()
                    WHERE product_id = '" . (int)$product_id . "'";
        $this->_module->db->query($sql);
        return true;
    }
    
    /**
     *  Get product price
     *  @param array data
     *  @return float
     */
    public function getPrice($data) {
        $price = $data['price'];
        if ((int) $data['vatIncluded'] === 1) {
            $price /= 1 + $data['vatPercentage'] / 100;
        }
        return round($price, 4);
    }
    
    /**
     *  Get Tax Rules Group Id
     *  @param float rate
     *  @return int
     */
    public function getTaxClassId($rate, $based = 'shipping') {
        $sql = "SELECT trl.tax_class_id FROM " . DB_PREFIX . "tax_rate tr
                JOIN " . DB_PREFIX . "geo_zone gz ON(gz.geo_zone_id=tr.geo_zone_id)
                JOIN " . DB_PREFIX . "tax_rule trl ON(trl.tax_rate_id=tr.tax_rate_id)
                JOIN " . DB_PREFIX . "tax_class tc ON(trl.tax_class_id=tc.tax_class_id)
                WHERE
                    tr.rate='" . $this->_module->db->escape($rate) . "' AND
                    trl.based='" . $this->_module->db->escape($based) . "'";
        $query = $this->_module->db->query($sql);
        return empty($query->row) ? 0 : (int) $query->row['tax_class_id'];
    }
}