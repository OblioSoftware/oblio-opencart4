<?php

namespace Opencart\Admin\Controller\Extension\Oblio\Module;

use Exception;

/**
 * Class Oblio
 *
 * @package Opencart\Admin\Controller\Extension\Oblio\Module
 */
class Oblio extends \Opencart\System\Engine\Controller {
    private $_name               = 'oblio';
    private $_table_invoice      = 'order_oblio_invoice';
    private $_table_product_type = 'product_oblio_type';
    private $_product_types      = [
        ['name' => 'Marfa'],
        ['name' => 'Semifabricate'],
        ['name' => 'Produs finit'],
        ['name' => 'Produs rezidual'],
        ['name' => 'Produse agricole'],
        ['name' => 'Animale si pasari'],
        ['name' => 'Ambalaje'],
        ['name' => 'Serviciu'],
    ];
    private $_no_yes           = [
        ['name' => 'Nu'],
        ['name' => 'Da'],
    ];
    private $_use_code           = [
        ['name' => 'Model', 'value' => 'model'],
        ['name' => 'SKU', 'value' => 'sku'],
        ['name' => 'UPC', 'value' => 'upc'],
        ['name' => 'EAN', 'value' => 'ean'],
        ['name' => 'JAN', 'value' => 'jan'],
        ['name' => 'ISBN', 'value' => 'isbn'],
        ['name' => 'MPN', 'value' => 'mpn'],
    ];
    private $error          = array();
  
    public function index() {
        $this->load->language('extension/oblio/module/oblio');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->load->model('setting/setting');
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_oblio', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/oblio/module/oblio', $this->getToken(), true));
        }
        
        $data['heading_title'] = $this->language->get('heading_title');

        $data['entry_email'] = $this->language->get('entry_email');
        $data['entry_api_secret'] = $this->language->get('entry_api_secret');
        $data['entry_status'] = $this->language->get('entry_status');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        
        $data['action'] = $this->url->link('extension/oblio/module/oblio', $this->getToken(), true);
        $data['cancel'] = $this->url->link('marketplace/extension', $this->getToken(), true);
        
        $data['module_oblio_email'] = $this->config->get('module_oblio_email');
        $data['module_oblio_api_secret'] = $this->config->get('module_oblio_api_secret');
        $data['module_oblio_status'] = $this->config->get('module_oblio_status');
        
        $data['module_oblio_company_cui'] = $this->config->get('module_oblio_company_cui');
        $data['module_oblio_company_series_name'] = $this->config->get('module_oblio_company_series_name');
        $data['module_oblio_company_series_name_proforma'] = $this->config->get('module_oblio_company_series_name_proforma');
        $data['module_oblio_company_workstation'] = $this->config->get('module_oblio_company_workstation');
        $data['module_oblio_company_management'] = $this->config->get('module_oblio_company_management');
        $data['module_oblio_product_type'] = $this->config->get('module_oblio_product_type');
        $data['module_oblio_use_code'] = $this->config->get('module_oblio_use_code');
        $data['module_oblio_send_email'] = $this->config->get('module_oblio_send_email');
        $data['module_oblio_use_stock'] = $this->config->get('module_oblio_use_stock');
        $data['module_oblio_vat_shipping'] = $this->config->get('module_oblio_vat_shipping');
        $data['module_oblio_discount_separate_lines'] = $this->config->get('module_oblio_discount_separate_lines');
        
        // get API data
        $fields = [];
        $accessTokenHandler = new AccessTokenHandler($this);
        if ($data['module_oblio_email'] && $data['module_oblio_api_secret']) {
            try {
                $api = new Api($data['module_oblio_email'], $data['module_oblio_api_secret'], $accessTokenHandler);
                
                $series         = [];
                $seriesProforma = [];
                $companies      = [];
                $workStations   = [];
                $management     = [];
                // companies
                $response = $api->nomenclature('companies');
                if ((int) $response['status'] === 200 && count($response['data']) > 0) {
                    $cui = $data['module_oblio_company_cui'];
                    $useStock = false;
                    foreach ($response['data'] as $company) {
                        $companies[] = $company;
                        if ($company['cif'] === $cui) {
                            $useStock = $company['useStock'];
                        }
                    }
                    $fields = array(
                        array(
                            'type' => 'select',
                            'label' => 'Companie',
                            'name' => 'module_oblio_company_cui',
                            'options' => [
                                'query' => array_merge([['cif' => '', 'company' => 'Selecteaza']], $companies),
                                'id'    => 'cif',
                                'name'  => 'company',
                                'data'  => array('use-stock' => 'useStock'),
                            ],
                            'class' => 'chosen',
                            'selected' => $cui,
                            //'lang' => true,
                            'required' => true
                        ),
                    );
                    
                    if ($cui) {
                        $api->setCif($cui);
                        
                        // series
                        sleep(1); // 1s sleep
                        $response = $api->nomenclature('series', '', ['type' => 'Factura']);
                        $series = $response['data'];

                        // series
                        /*sleep(1); // 1s sleep
                        $response = $api->nomenclature('series', '', ['type' => 'Proforma']);
                        $seriesProforma = $response['data'];//*/
                        
                        // management
                        if ($useStock) {
                            sleep(1); // 1s sleep
                            $response = $api->nomenclature('management', '');
                            foreach ($response['data'] as $item) {
                                if ($data['module_oblio_company_workstation'] === $item['workStation']) {
                                    $management[] = ['name' => $item['management']];
                                }
                                $workStations[$item['workStation']] = ['name' => $item['workStation']];
                            }
                        }
                    }
                    
                    $fields[] = array(
                        'type' => 'select',
                        'label' => 'Serie factura',
                        'name' => 'module_oblio_company_series_name',
                        'options' => [
                            'query' => array_merge([['name' => 'Selecteaza']], $series),
                            'id'    => 'name',
                            'name'  => 'name',
                        ],
                        'class' => 'chosen',
                        'selected' => $data['module_oblio_company_series_name'],
                        //'lang' => true,
                        'required' => true
                    );
                    /*$fields[] = array(
                        'type' => 'select',
                        'label' => 'Serie proforma',
                        'name' => 'module_oblio_company_series_name_proforma',
                        'options' => [
                            'query' => array_merge([['name' => 'Selecteaza']], $seriesProforma),
                            'id'    => 'name',
                            'name'  => 'name',
                        ],
                        'class' => 'chosen',
                        'selected' => $data['module_oblio_company_series_name_proforma'],
                        //'lang' => true,
                        'required' => true
                    );//*/
                    $fields[] = array(
                        'type' => 'select',
                        'label' => 'Punct de lucru',
                        'name' => 'module_oblio_company_workstation',
                        'options' => [
                            'query' => array_merge([['name' => 'Selecteaza']], $workStations),
                            'id'    => 'name',
                            'name'  => 'name',
                        ],
                        'class' => 'chosen',
                        'selected' => $data['module_oblio_company_workstation'],
                        //'lang' => true,
                        //'required' => true
                    );
                    $fields[] = array(
                        'type' => 'select',
                        'label' => 'Gestiune',
                        'name' => 'module_oblio_company_management',
                        'options' => [
                            'query' => array_merge([['name' => 'Selecteaza']], $management),
                            'id'    => 'name',
                            'name'  => 'name',
                        ],
                        'class' => 'chosen',
                        'selected' => $data['module_oblio_company_management'],
                        //'lang' => true,
                        //'required' => true
                    );
                }
            } catch (Exception $e) {
                $data['message_error_api'] = $e->getMessage();
            }
        }
        
        $fields[] = array(
            'type' => 'select',
            'label' => 'Tip produs',
            'name' => 'module_oblio_product_type',
            'options' => [
                'query' => $this->_product_types,
                'id'    => 'name',
                'name'  => 'name',
            ],
            'class' => 'chosen',
            'selected' => $data['module_oblio_product_type'],
            //'lang' => true,
            //'required' => true
        );
        
        $fields[] = array(
            'type' => 'select',
            'label' => 'Cod de produs folosit pentru sincronizare',
            'name' => 'module_oblio_use_code',
            'options' => [
                'query' => $this->_use_code,
                'id'    => 'value',
                'name'  => 'name',
            ],
            'class' => 'chosen',
            'selected' => $data['module_oblio_use_code'],
            //'lang' => true,
            //'required' => true
        );
        
        $fields[] = array(
            'type' => 'select',
            'label' => 'Trimite email la generare factura',
            'name' => 'module_oblio_send_email',
            'options' => [
                'query' => $this->_no_yes,
                'id'    => 'name',
                'name'  => 'name',
            ],
            'class' => 'chosen',
            'selected' => $data['module_oblio_send_email'],
            //'lang' => true,
            //'required' => true
        );

        $fields[] = array(
            'type' => 'select',
            'label' => 'Descarca gestiunea la generare factura',
            'name' => 'module_oblio_use_stock',
            'options' => [
                'query' => $this->_no_yes,
                'id'    => 'name',
                'name'  => 'name',
            ],
            'class' => 'chosen',
            'selected' => $data['module_oblio_use_stock'],
            //'lang' => true,
            //'required' => true
        );
        
        $fields[] = array(
            'type' => 'text',
            'label' => 'TVA Transport',
            'name' => 'module_oblio_vat_shipping',
            'class' => '',
            'value' => isset($data['module_oblio_vat_shipping']) ? $data['module_oblio_vat_shipping'] : 19,
            //'lang' => true,
            //'required' => true
        );

        $fields[] = array(
            'type' => 'select',
            'label' => 'Discount pe liniii separate',
            'name' => 'module_oblio_discount_separate_lines',
            'options' => [
                'query' => $this->_no_yes,
                'id'    => 'name',
                'name'  => 'name',
            ],
            'class' => 'chosen',
            'selected' => $data['module_oblio_discount_separate_lines'] ? $data['module_oblio_discount_separate_lines'] : 'Nu',
            //'lang' => true,
            //'required' => true
        );
        
        // fieldsets
        $fieldSets = [];
        $fieldSets[] = [
            'name'   => $this->language->get('text_settings'),
            'fields' => $fields,
        ];

        $invoiceOptionsFields = [];
        $fieldsList = ['issuer_name', 'issuer_id', 'deputy_name', 'deputy_identity_card', 'deputy_auto', 'seles_agent', 'mentions'];
        foreach ($fieldsList as $fieldName) {
            $keyName = 'module_oblio_invoice_' . $fieldName;
            $data[$keyName] = $this->config->get($keyName);
            $invoiceOptionsFields[] = [
                'type'          => in_array($fieldName, ['mentions']) ? 'textarea' : 'text',
                'label'         => $this->language->get('entry_' . $fieldName),
                'name'          => $keyName,
                'value'         => $data[$keyName],
                'description'   => in_array($fieldName, ['mentions']) ? '[order_id] = numar comanda<br> [date] = data comanda' : '',
            ];
        }
        $fieldSets[] = [
            'name'   => $this->language->get('text_invoice_options'),
            'fields' => $invoiceOptionsFields,
        ];
        $data['fieldSets'] = $fieldSets;
        
        $data['ajax_link'] = htmlspecialchars_decode($this->url->link('extension/oblio/module/oblio.ajax_import', $this->getToken(), true));
        
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', $this->getToken(), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', $this->getToken(), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/oblio/module/oblio', $this->getToken(), true)
        );
        $data['error']          = $this->error;
        $data['header']         = $this->load->controller('common/header');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['footer']         = $this->load->controller('common/footer');
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            $this->session->data['success'] = null;
        }
        
        $this->response->setOutput($this->load->view('extension/oblio/module/oblio', $data));
    }
    
    public function import() {
        $this->load->language('extension/oblio/module/oblio');
        
        $this->document->setTitle($this->language->get('heading_title'));
        $page_name = 'Sincronizare produse';
        $data['page_name'] = $page_name;
        
        $data['ajax_link'] = htmlspecialchars_decode($this->url->link('extension/oblio/module/oblio.ajax_import', $this->getToken(), true));
        $data['cron_link'] = $this->url->link('marketplace/cron', $this->getToken(), true);
        
        srand((int) substr(preg_replace('/[a-z]/i', '', md5(HTTP_SERVER)), 0, 8));
        $data['cron_minute'] = rand(0, 59); // fixed for this domain
        $data['dir_oc'] = DIR_OPENCART;
        $data['secret'] = $this->config->get('module_oblio_api_secret');
        srand(time());
        
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', $this->getToken(), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', $this->getToken(), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/oblio/module/oblio', $this->getToken(), true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $page_name,
            'href' => $this->url->link('extension/oblio/module/oblio.import', $this->getToken(), true)
        );
        $data['error']          = $this->error;
        $data['header']         = $this->load->controller('common/header');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['footer']         = $this->load->controller('common/footer');
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            $this->session->data['success'] = null;
        }
        
        $this->response->setOutput($this->load->view('extension/oblio/module/oblio_import', $data));
    }
    
    public function product_types() {
        $this->load->language('extension/oblio/module/oblio');
        
        $this->document->setTitle($this->language->get('heading_title'));
        $page_name = 'Tip produse';
        $data['page_name'] = $page_name;
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            switch ($this->request->post['submit']) {
                case 'add':
                    if (!empty($this->request->post['product_id'])) {
                        $sql = "INSERT INTO " . DB_PREFIX . $this->_table_product_type . " (product_id, product_type)
                                VALUES ('" . $this->db->escape($this->request->post['product_id']) . "', '" . $this->db->escape($this->request->post['product_type']) . "')
                                ON DUPLICATE KEY UPDATE product_type='" . $this->db->escape($this->request->post['product_type']) . "'";
                        $this->db->query($sql);
                    }
                    break;
                case 'delete':
                    if (isset($this->request->post['prod'])) {
                        foreach ($this->request->post['prod'] as $product_id => $value) {
                            $sql = "DELETE FROM " . DB_PREFIX . $this->_table_product_type . " WHERE product_id=" . (int) $product_id;
                            $this->db->query($sql);
                        }
                    }
                    break;
            }
            
            
            $this->response->redirect($this->url->link('extension/oblio/module/oblio.product_types', $this->getToken(), true));
        }
        
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', $this->getToken(), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', $this->getToken(), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/oblio/module/oblio', $this->getToken(), true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $page_name,
            'href' => $this->url->link('extension/oblio/module/oblio.product_types', $this->getToken(), true)
        );
        $data['products_list']              = $this->getProductsList();
        $data['products_list_custom_type']  = $this->getProductsWithCustomType();
        $data['products_types']             = $this->_product_types;
        
        $data['error']          = $this->error;
        $data['header']         = $this->load->controller('common/header');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['footer']         = $this->load->controller('common/footer');
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            $this->session->data['success'] = null;
        }
        
        $this->response->setOutput($this->load->view('extension/oblio/module/oblio_product_types', $data));
    }

    public function ajax_import() {
        $type = isset($this->request->post['type']) ? $this->request->post['type'] : '';
        $cui  = isset($this->request->post['cui']) ? $this->request->post['cui'] : '';
        $name = isset($this->request->post['name']) ? $this->request->post['name'] : '';
        
        $data = [];
        switch ($type) {
            case 'series_name':
            case 'workstation':
            case 'management':
                $data = $this->getApiData([
                    'type' => $type,
                    'cui'  => $cui,
                    'name' => $name
                ]);
                break;
            default:
                $total = $this->syncStock($error);
                $data = [$total, $error];
        }
        echo json_encode($data);
    }

    public function validate() {
        if (!$this->user->hasPermission('modify', 'extension/oblio/module/oblio')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
 
    public function install() {
        // add events
        $this->load->model('setting/event');

        $this->model_setting_event->addEvent([
            'code'          => 'oblio_add_menu',
            'description'   => '',
            'trigger'       => 'admin/view/common/column_left/before',
            'action'        => 'extension/oblio/module/oblio.injectAdminMenuItem',
            'status'        => '1',
            'sort_order'    => '1',
        ]);
        $this->model_setting_event->addEvent([
            'code'          => 'oblio_add_menu',
            'description'   => '',
            'trigger'       => 'admin/controller/sale/order/invoice/before',
            'action'        => 'extension/oblio/module/oblio.generate',
            'status'        => '1',
            'sort_order'    => '1',
        ]);
        $this->model_setting_event->addEvent([
            'code'          => 'oblio_add_menu',
            'description'   => '',
            'trigger'       => 'admin/controller/sale/order/createinvoiceno/after',
            'action'        => 'extension/oblio/module/oblio.generate',
            'status'        => '1',
            'sort_order'    => '1',
        ]);
        $this->model_setting_event->addEvent([
            'code'          => 'oblio_add_menu',
            'description'   => '',
            'trigger'       => 'admin/view/sale/order_info/before',
            'action'        => 'extension/oblio/module/oblio.addOrderVars',
            'status'        => '1',
            'sort_order'    => '1',
        ]);
        
        // add custom field (vat number)
        $this->load->model('customer/customer_group');
        $groups = $this->model_customer_customer_group->getCustomerGroups();
        $default_customer_group_id = 0;
        foreach ($groups as $group) {
            if ($group['name'] === 'Default') {
                $default_customer_group_id = (int)$group['customer_group_id'];
                break;
            }
        }
        
        $this->load->model('customer/custom_field');
        $field_names = ['CUI', 'CIF'];
        foreach ($field_names as $field_name) {
            $filter_data = [
                'filter_name' => $field_name,
            ];
            $fields = $this->model_customer_custom_field->getCustomFields($filter_data);
            $found = false;
            foreach ($fields as $field) {
                if ($field['name'] === $field_name) {
                    $found = true;
                    break 2;
                }
            }
        }
        if (!$found) {
            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();
            $names = [];
            foreach ($languages as $language) {
                $names[$language['language_id']] = ['name' => $field_name];
            }
            $data = [
                'type'       => 'text',
                'value'      => '',
                'validation' => '',
                'location'   => 'address',
                'status'     => '1',
                'sort_order' => '1',
                'custom_field_customer_group' => [
                    [
                        'customer_group_id' => $default_customer_group_id
                    ]
                ],
                'custom_field_description' => $names,
                'custom_field_value' => [
                    [
                        'custom_field_value_description' => $names,
                        'sort_order' => 1,
                    ]
                ],
            ];
            $custom_field_id = $this->model_customer_custom_field->addCustomField($data);
        }
        
        // check product types table
        $this->_checkTableType();

        // check invoice table
        $this->_checkTableInvoice();

        // add cron tab
        $this->_addCronTab();
        
        // activate module
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_oblio', ['module_oblio_status' => 1]);
    }
 
    public function uninstall() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('oblio_add_menu');
        $this->model_setting_event->deleteEventByCode('oblio_view_invoice');
        $this->model_setting_event->deleteEventByCode('oblio_create_invoice');
        $this->model_setting_event->deleteEventByCode('oblio_add_order_vars');
        
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_oblio');
    }

    public function addOrderVars($eventRoute, &$data) {
        $this->load->model('sale/order');
        $order_id = (int) ($this->request->get['order_id'] ?? 0);
        // $order_info = $this->model_sale_order->getOrder($order_id);
        $invoiceData = $this->getInvoiceData($order_id);
        $isLastInvoice = true;
        $btnText = '';
        $btnClass = 'oblio-generate-invoice';
        $generateBtnClass = '';
        $deleteBtnClass = ' d-none';
        $linkInvoice = $this->url->link('extension/oblio/module/oblio.generate_invoice', $this->getToken() . '&order_id=' . $this->request->get['order_id'], true);
        $linkDeleteInvoice = $this->url->link('extension/oblio/module/oblio.delete_invoice', $this->getToken() . '&order_id=' . $this->request->get['order_id'], true);
        $linkDeleteInvoice = htmlspecialchars_decode($linkDeleteInvoice);
        if ($invoiceData && $invoiceData['number'] > 0) {
            $isLastInvoice = $this->isLastInvoice($invoiceData);
            $generateBtnClass = 'd-none';
            $deleteBtnClass = $isLastInvoice ? '' : 'd-none';
            $btnText = sprintf('Factura %s%d', $invoiceData['series_name'], $invoiceData['number']);
            $btnClass = 'oblio-view-invoice';
            $linkInvoice = $this->url->link('extension/oblio/module/oblio.view_invoice', $this->getToken() . '&order_id=' . $this->request->get['order_id'], true);
        }
        $linkInvoice = htmlspecialchars_decode($linkInvoice);

        $script = <<<SCRIPT
        <script>
        $(document).ready(function() {
            var container = $('#content .page-header .float-end'), oblioContainer = $('<div/>');
            console.log(container);
            oblioContainer.addClass('float-start');
            container.append(oblioContainer);
            $('#content > .container-fluid').prepend(
                $('<div>').attr('class', 'row').append(
                    $('<div>')
                        .attr('id', 'oblio-response')
                        .attr('class', 'col-md-12')
                )
            );

            function createButton(options) {
                var btn = $('<a/>');
                options.icon = options.icon || 'fa fa-file';
                options.text = options.text || '';
                btn.addClass('btn btn-info');
                btn.addClass(options.class);
                btn.html(`<i class="\${options.icon}"></i> \${options.text}`);
                btn.attr('title', options.name);
                btn.attr('data-toggle', 'tooltip');
                btn.attr('href', options.link);
                btn.attr('target', '_blank');
                return btn;
            }
            function appendButton(options) {
                oblioContainer.append(createButton(options));
                oblioContainer.append('&nbsp;');
            }
            appendButton({
                name: 'Emite factura cu Oblio',
                class: '{$btnClass}',
                icon: 'fa-regular fa-file-pdf',
                link: '{$linkInvoice}&use_stock=1',
                text: '{$btnText}'
            });
            appendButton({
                name: 'Emite factura cu Oblio fara descarcare',
                class: 'oblio-generate-invoice {$generateBtnClass}',
                link: '{$linkInvoice}'
            });
            appendButton({
                name: 'Sterge factura',
                class: 'oblio-delete-invoice {$deleteBtnClass}',
                icon: 'fa fa-remove',
                link: '{$linkDeleteInvoice}'
            });
        });

        $(document).ready(function() {
            var buttons = $('.oblio-generate-invoice'),
                deleteButton = $('.oblio-delete-invoice'),
                viewButton = $('.oblio-view-invoice'),
                responseContainer = $('#oblio-response');
            buttons.click(function(e) {
                var self = $(this);
                if (self.hasClass('disabled')) {
                    return false;
                }
                if (!self.hasClass('oblio-generate-invoice')) {
                    return true;
                }
                e.preventDefault();
                self.addClass('disabled');
                jQuery.ajax({
                    dataType: 'json',
                    url: self.attr('href'),
                    data: {},
                    success: function(response) {
                        var alertMsg = '';
                        self.removeClass('disabled');
                        
                        if ('link' in response) {
                            buttons.not(self).hide();
                            self
                                .attr('href', response.link)
                                .removeClass('oblio-generate-invoice')
                                .text(`Vezi factura \${response.seriesName} \${response.number}`);
                                alertMsg = '<div class="alert alert-success">FACTURA a fost emisa</div>';
                            deleteButton.removeClass('d-none');
                        } else if ('error' in response) {
                            alertMsg = '<div class="alert alert-danger">' + response.error + '</div>';
                        }
                        responseContainer.html(alertMsg);
                    }
                });
            });
            deleteButton.click(function(e) {
                var self = $(this);
                if (self.hasClass('disabled')) {
                    return false;
                }
                e.preventDefault();
                self.addClass('disabled');
                jQuery.ajax({
                    dataType: 'json',
                    url: self.attr('href'),
                    data: {},
                    success: function(response) {
                        if (response.type == 'success') {
                            location.reload();
                        } else {
                            var alertMsg = `<div class="alert alert-\${response.type}">\${response.message}</div>`;
                            responseContainer.html(alertMsg);
                            self.removeClass('disabled');
                        }
                    }
                });
            });
        });
        </script>
SCRIPT;
        $data['footer'] = $script . $data['footer'];
    }
    
    public function injectAdminMenuItem($eventRoute, &$data) {
        $children = [];
        $children['oblio-settings'] = [
            'id'        => 'oblio-settings',
            'icon'      => '',
            'name'      => 'Setari',
            'href'      => $this->url->link('extension/oblio/module/oblio', $this->getToken(), true),
            'children'  => []
        ];
        $children['oblio-import'] = [
            'id'        => 'oblio-import',
            'icon'      => '',
            'name'      => 'Sincronizare produse',
            'href'      => $this->url->link('extension/oblio/module/oblio.import', $this->getToken(), true),
            'children'  => []
        ];
        $children['oblio-product-types'] = [
            'id'        => 'oblio-product-types',
            'icon'      => '',
            'name'      => 'Tip produse',
            'href'      => $this->url->link('extension/oblio/module/oblio.product_types', $this->getToken(), true),
            'children'  => []
        ];
        
        $data['menus'][] = [
            'id'        => 'oblio-menu',
            'icon'      => 'fa fa-file fa-fw',
            'name'      => 'Oblio',
            'href'      => '',
            'children'  => $children
        ];
    }

    public function view_invoice() {
        $use_stock  = isset($settings['module_oblio_use_stock']) ? $settings['module_oblio_use_stock'] : 'Nu';
        $data = $this->generateInvoice([
            'orderId' => (int) ($this->request->get['order_id'] ?? 0),
            'docType' => 'invoice',
            'useStock' => $use_stock === 'Da',
        ]);
        $this->response->redirect($data['link']);
    }

    public function generate_invoice() { // generare factura
        $use_stock  = (int) ($this->request->get['use_stock'] ?? 0);
        $data = $this->generateInvoice([
            'orderId' => (int) ($this->request->get['order_id'] ?? 0),
            'docType' => 'invoice',
            'useStock' => $use_stock,
        ]);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function delete_invoice() {
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('module_oblio');
        
        $cui                    = isset($settings['module_oblio_company_cui']) ? $settings['module_oblio_company_cui'] : null;
        $email                  = isset($settings['module_oblio_email']) ? $settings['module_oblio_email'] : null;
        $secret                 = isset($settings['module_oblio_api_secret']) ? $settings['module_oblio_api_secret'] : null;

        if (!$cui || !$email || !$secret) {
            return ['error' => 'Eroare configurare, intra la Oblio &gt; Setari'];
        }
        $this->load->model('sale/order');

        $order_id = (int) ($this->request->get['order_id'] ?? 0);
        $invoiceData = $this->getInvoiceData($order_id);
        if ($invoiceData && $invoiceData['number'] > 0) {
            try {
                $accessTokenHandler = new AccessTokenHandler($this);
                $api = new Api($email, $secret, $accessTokenHandler);
                $api->setCif($cui);

                $response = $api->delete('invoice', $invoiceData['series_name'], $invoiceData['number']);
                $data = [
                    'type'    => 'success',
                    'message' => $response['statusMessage']
                ];

                $this->setInvoiceData($order_id, [
                    'seriesName'    => '',
                    'number'        => '',
                    'link'          => '',
                ]);
            } catch (\Exception $e) {
                $data = [
                    'type'    => 'danger',
                    'message' => $e->getMessage()
                ];
            }
        } else {
            $data = [
                'type'    => 'danger',
                'message' => 'Nu exista factura pentru comanda #' . $order_id
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function generate($eventRoute, &$data) {
        /*$use_stock  = isset($settings['module_oblio_use_stock']) ? $settings['module_oblio_use_stock'] : 'Nu';
        $data = $this->generateInvoice([
            'orderId' => ($this->request->get['order_id'] ?? 0),
            'docType' => 'invoice',
            'useStock' => $use_stock === 'Da',
        ]);
        if ($eventRoute === 'sale/order/createinvoiceno') {
            $json = ['invoice_no' => $data['seriesName'] . ' ' . $data['number']];

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        } else {
            $this->response->redirect($data['link']);
        }//*/
    }
    
    public function generateInvoice($options = []) {
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('module_oblio');
        
        $cui                        = isset($settings['module_oblio_company_cui']) ? $settings['module_oblio_company_cui'] : null;
        $email                      = isset($settings['module_oblio_email']) ? $settings['module_oblio_email'] : null;
        $secret                     = isset($settings['module_oblio_api_secret']) ? $settings['module_oblio_api_secret'] : null;
        $series_name                = isset($settings['module_oblio_company_series_name']) ? $settings['module_oblio_company_series_name'] : null;
        $series_name_proforma       = isset($settings['module_oblio_company_series_name_proforma']) ? $settings['module_oblio_company_series_name_proforma'] : null;
        $workstation                = isset($settings['module_oblio_company_workstation']) ? $settings['module_oblio_company_workstation'] : null;
        $management                 = isset($settings['module_oblio_company_management']) ? $settings['module_oblio_company_management'] : null;
        $use_code                   = isset($settings['module_oblio_use_code']) ? $settings['module_oblio_use_code'] : 'model';
        $send_email                 = isset($settings['module_oblio_send_email']) ? $settings['module_oblio_send_email'] : 'Nu';
        $vat_shipping               = isset($settings['module_oblio_vat_shipping']) ? $settings['module_oblio_vat_shipping'] : 19;
        $discount_separate_lines    = isset($settings['module_oblio_discount_separate_lines']) ? $settings['module_oblio_discount_separate_lines'] === 'Da' : false;

        if (!$cui || !$email || !$secret) {
            return ['error' => 'Eroare configurare, intra la Oblio &gt; Setari'];
        }
        $language_id = (int) $this->config->get('config_language_id');

        if (empty($options['docType'])) {
            $options['docType'] = 'invoice';
        }
        
        $this->load->model('sale/order');

        $order_id = $options['orderId'];

        $order_info = $this->model_sale_order->getOrder($order_id);
        $invoiceData = $this->getInvoiceData($order_id);
        if ($invoiceData && $invoiceData['number'] > 0) {
            try {
                $accessTokenHandler = new AccessTokenHandler($this);
                $api = new Api($email, $secret, $accessTokenHandler);
                $api->setCif($cui);

                $invoice = $api->get('invoice', $invoiceData['series_name'], $invoiceData['number']);
                return $invoice['data'];
            } catch (\Exception $e) {
                // delete old
                $this->setInvoiceData($order_id, [
                    'seriesName'    => '',
                    'number'        => '',
                    'link'          => '',
                ]);
            }
        }
        try {
            if (strtolower($order_info['currency_code']) === 'lei') {
                $order_info['currency_code'] = 'RON';
            }
            $contact = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
            
            $this->load->model('customer/custom_field');
            $clientCif = '';
            foreach ($order_info['payment_custom_field'] as $custom_field_id => $custom_field_value) {
                $custom_field = $this->model_customer_custom_field->getCustomFieldDescriptions($custom_field_id);
                
                if (preg_match('/(cif|cui)/i', $custom_field[$language_id]['name'])) {
                    $clientCif = $order_info['payment_custom_field'][$custom_field_id];
                    break;
                }
            }

            $replace = [
                '[order_id]'    => $order_id,
                '[date]'        => date('d.m.Y', strtotime($order_info['date_added'])),
            ];
            $mentions = $this->config->get('module_oblio_invoice_mentions');
            $mentions = str_replace(array_keys($replace), array_values($replace), $mentions);
            
            $invData = array(
                'cif'                => $cui,
                'client'             => [
                    'cif'           => $clientCif,
                    'name'          => empty($order_info['payment_company']) ? $contact : $order_info['payment_company'],
                    'rc'            => '',
                    'code'          => '',
                    'address'       => trim($order_info['payment_address_1'] . ' ' . $order_info['payment_address_2']),
                    'state'         => $order_info['payment_zone'],
                    'city'          => $order_info['payment_city'],
                    'country'       => $order_info['payment_country'],
                    'iban'          => '',
                    'bank'          => '',
                    'email'         => $order_info['email'],
                    'phone'         => $order_info['telephone'],
                    'contact'       => $contact,
                    'vatPayer'      => preg_match('/^RO/i', $clientCif),
                ],
                'issueDate'          => date('Y-m-d'), // strtotime($order_info['date_added'])
                'dueDate'            => '',
                'deliveryDate'       => '',
                'collectDate'        => '',
                'seriesName'         => $options['docType'] === 'proforma' ?  $series_name_proforma : $series_name,
                'collect'            => [],
                'referenceDocument'  => [],
                'language'           => 'RO',
                'precision'          => 2,
                'currency'           => $order_info['currency_code'],
                'products'           => [],
                'issuerName'         => $this->config->get('module_oblio_invoice_issuer_name'),
                'issuerId'           => $this->config->get('module_oblio_invoice_issuer_id'),
                'noticeNumber'       => '',
                'internalNote'       => '',
                'deputyName'         => $this->config->get('module_oblio_invoice_deputy_name'),
                'deputyIdentityCard' => $this->config->get('module_oblio_invoice_deputy_identity_card'),
                'deputyAuto'         => $this->config->get('module_oblio_invoice_deputy_auto'),
                'selesAgent'         => $this->config->get('module_oblio_invoice_seles_agent'),
                'mentions'           => $mentions,
                'value'              => 0,
                'workStation'        => $workstation,
                'sendEmail'          => $send_email === 'Da',
                'useStock'           => !empty($options['useStock']),
            );
            $products = $this->getOrderProducts($order_id);
            $vatPercentage = 0;
            $totalValue = 0;
            foreach ($products as $item) {
                $orderOptions = $this->model_sale_order->getOptions($order_id, $item['order_product_id']);

                $option_data = array();
                $description = '';
                foreach ($orderOptions as $option) {
                    if ($option['type'] != 'file') {
                        $option_data[] = array(
                            'name'  => $option['name'],
                            'value' => $option['value'],
                            'type'  => $option['type']
                        );
                        $description .= sprintf(" - %s: %s\n", $option['name'], $option['value']);
                    }
                }
                
                $vatPercentage = ($item['tax'] > 0) ? round($item['tax'] / $item['price'] * 100) : 19;
                if (!$this->config->get('config_tax')) {
                    $vatPercentage = 0;
                }

                $price     = round(($item['price'] + $item['tax']) * $order_info['currency_value'], $invData['precision']);
                $fullPrice = $this->getPriceWithVat($item['full_price'] * $order_info['currency_value'], $vatPercentage);

                $invData['products'][] = [
                    'name'          => $item['name'],
                    'code'          => $item[$use_code],
                    'description'   => $description,
                    'price'         => $discount_separate_lines ? $fullPrice : $price,
                    'currency'      => $order_info['currency_code'],
                    'exchangeRate'  => 1 / $order_info['currency_value'],
                    'measuringUnit' => 'buc',
                    'vatName'       => $vatPercentage ? '' : 'SDD',
                    'vatPercentage' => $vatPercentage,
                    'vatIncluded'   => 1,
                    'quantity'      => $item['quantity'],
                    'productType'   => $this->getProductType($item['product_id']),
                    'management'    => $management,
                ];
                $totalValue += round($price * $item['quantity'], 4);

                if ($discount_separate_lines && number_format($fullPrice, 4) !== number_format($price, 4)) {
                    $discount = ($fullPrice * $item['quantity']) - ($price * $item['quantity']);
                    $discount = round($discount, $invData['precision'], PHP_ROUND_HALF_DOWN);
                    if ($discount > 0) {
                        $invData['products'][] = [
                            'name'          => sprintf('Discount "%s"', $item['name']),
                            'discount'      => $discount,
                            'discountType'  => 'valoric',
                        ];
                    }
                }
            }
            
            $vouchers = $this->getOrderVouchers($order_id);
            foreach ($vouchers as $item) {
                $invData['products'][] = [
                    'name'          => $item['description'],
                    'code'          => 'voucher',
                    'description'   => $item['code'],
                    'price'         => round($item['amount'], $invData['precision']),
                    'currency'      => $order_info['currency_code'],
                    'exchangeRate'  => 1 / $order_info['currency_value'],
                    'measuringUnit' => 'buc',
                    'vatName'       => 'SDD',
                    'vatPercentage' => 0,
                    'vatIncluded'   => 1,
                    'quantity'      => 1,
                    'productType'   => 'Serviciu',
                    'management'    => $management,
                ];
                $totalValue += round(end($invData['products'])['price'], 4);
            }
            
            $info = [];
            $totals = $this->model_sale_order->getTotals($order_id);
            foreach ($totals as $total) {
                switch ($total['code']) {
                    case 'shipping':
                        $info['shipping'] = $total;
                        break;
                    case 'coupon':
                        $info['discount'] = $total;
                        break;
                    case 'tax':
                        $info['tax'] = $total;
                        break;
                    case 'total':
                        $info['total'] = $total;
                        break;
                }
            }
            
            if (!empty($info['shipping'])) {
                $invData['products'][] = [
                    'name'          => 'Transport',
                    'code'          => '',
                    'description'   => '',
                    'price'         => round($info['shipping']['value'] * $order_info['currency_value'], $invData['precision']),
                    'currency'      => $order_info['currency_code'],
                    'exchangeRate'  => 1 / $order_info['currency_value'],
                    'measuringUnit' => 'buc',
                    'vatName'       => $vat_shipping ? '' : 'SDD',
                    'vatPercentage' => $vat_shipping,
                    'vatIncluded'   => 1,
                    'quantity'      => 1,
                    'productType'   => 'Serviciu',
                ];
                $totalValue += round(end($invData['products'])['price'], 4);
            }
            if (!empty($info['discount'])) {
                $invData['products'][] = [
                    'name'          => $info['discount']['title'],
                    'discount'      => round($info['discount']['value'] * (1 + $vatPercentage / 100), $invData['precision']),
                    'discountType'  => 'valoric',
                ];
                $totalValue += round(end($invData['products'])['discount'], 4);
            }
            if (number_format($totalValue, 2, '.', '') !== number_format($info['total']['value'], 2, '.', '')) {
                $invData['products'][] = [
                    'name'          => 'Alte taxe',
                    'code'          => '',
                    'description'   => '',
                    'price'         => round($info['total']['value'] - $totalValue, $invData['precision']),
                    'currency'      => $order_info['currency_code'],
                    'exchangeRate'  => 1 / $order_info['currency_value'],
                    'measuringUnit' => 'buc',
                    'vatName'       => $vat_shipping ? '' : 'SDD',
                    'vatPercentage' => $vat_shipping,
                    'vatIncluded'   => 1,
                    'quantity'      => 1,
                    'productType'   => 'Serviciu',
                ];
            }
            
            $accessTokenHandler = new AccessTokenHandler($this);
            $api = new Api($email, $secret, $accessTokenHandler);
            // create invoice:
            switch ($options['docType']) {
                case 'profoma': $result = $api->createProforma($invData); break;
                default: $result = $api->createInvoice($invData);
            }

            $this->setInvoiceData($order_id, $result['data']);
            
            return $result['data'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    public function syncStock(&$error = '') {
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('module_oblio');
        
        $cui            = isset($settings['module_oblio_company_cui']) ? $settings['module_oblio_company_cui'] : null;
        $email          = isset($settings['module_oblio_email']) ? $settings['module_oblio_email'] : null;
        $secret         = isset($settings['module_oblio_api_secret']) ? $settings['module_oblio_api_secret'] : null;
        $workstation    = isset($settings['module_oblio_company_workstation']) ? $settings['module_oblio_company_workstation'] : null;
        $management     = isset($settings['module_oblio_company_management']) ? $settings['module_oblio_company_management'] : null;
        
        if (!$email || !$secret || !$cui) {
            $error = 'Configurati modulul Oblio cu detaliile activitatii dumneavoastra.';
            return 0;
        }
        
        $total = 0;
        try {
            $accessTokenHandler = new AccessTokenHandler($this);
            $api = new Api($email, $secret, $accessTokenHandler);
            $api->setCif($cui);
            
            $offset = 0;
            $limitPerPage = 250;
            do {
                if ($offset > 0) {
                    usleep(500000);
                }
                $products = $api->nomenclature('products', null, [
                    'workStation' => $workstation,
                    'management'  => $management,
                    'offset'      => $offset,
                ]);
                
                $model = new Products($this);
                $index = 0;
                foreach ($products['data'] as $product) {
                    $index++;
                    $post = $model->find($product);
                    if ($post && $this->getProductType($post['product_id']) !== $product['productType']) {
                        continue;
                    }
                    if ($post) {
                        $model->update($post['product_id'], $product);
                    } else {
                        // $model->insert($product);
                    }
                }
                $offset += $limitPerPage; // next page
            } while ($index === $limitPerPage);
            $total = $offset - $limitPerPage + $index;
        } catch (Exception $e) {
            $error = $e->getMessage();
            // $accessTokenHandler->clear();
        }
        return $total;
    }
    
    public function getApiData($options) {
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('module_oblio');
        
        $type        = isset($options['type']) ? $options['type'] : '';
        $cui         = isset($options['cui']) ? $options['cui'] : '';
        $name        = isset($options['name']) ? $options['name'] : '';
        $result      = array();
        
        $email       = isset($settings['module_oblio_email']) ? $settings['module_oblio_email'] : null;
        $secret      = isset($settings['module_oblio_api_secret']) ? $settings['module_oblio_api_secret'] : null;
        
        if (!$email || !$secret) {
            return [];
        }
        
        try {
            $accessTokenHandler = new AccessTokenHandler($this);
            $api = new Api($email, $secret, $accessTokenHandler);
            $api->setCif($cui);
            
            switch ($type) {
                case 'series_name':
                    $response = $api->nomenclature('series', '', ['type' => 'Factura']);
                    $result = $response['data'];
                    break;
                case 'workstation':
                case 'management':
                    $response = $api->nomenclature('management', '');
                    $workStations = array();
                    $management = array();
                    foreach ($response['data'] as $item) {
                        if ($name === $item['workStation']) {
                            $management[] = ['name' => $item['management']];
                        }
                        $workStations[$item['workStation']] = ['name' => $item['workStation']];
                    }
                    switch ($type) {
                        case 'workstation': $result = $workStations; break;
                        case 'management': $result = $management; break;
                    }
                    break;
            }
        } catch (\Exception $e) {
            // do nothing
        }
        return $result;
    }
    
    public function getOrderProducts($order_id) {
        $sql = "SELECT op.*, p.sku, p.upc, p.ean, p.jan, p.isbn, p.mpn, p.price AS full_price FROM " . DB_PREFIX . "order_product op " .
            "LEFT JOIN " . DB_PREFIX . "product p ON(p.product_id=op.product_id) " .
            "WHERE op.order_id = '" . (int)$order_id . "'";
        $query = $this->db->query($sql);

        return $query->rows;
    }
    
    public function getOrderVouchers($order_id) {
        $sql = "SELECT * FROM " . DB_PREFIX . "order_voucher " .
            "WHERE order_id = '" . (int)$order_id . "'";
        $query = $this->db->query($sql);

        return $query->rows;
    }
    
    public function getProductType($product_id) {
        $settings = $this->model_setting_setting->getSetting('module_oblio');
        $product_type   = isset($settings['module_oblio_product_type']) ? $settings['module_oblio_product_type'] : 'Marfa';
        
        $product_id = (int) $product_id;
        $sql = "SELECT * FROM " . DB_PREFIX . $this->_table_product_type . " WHERE product_id={$product_id}";
        $query = $this->db->query($sql);
        return $query->num_rows ? $query->row['product_type'] : $product_type;
    }
    
    public function getProductsWithCustomType($language_id = 0) {
        $language_id = (int) $language_id;
        if (!$language_id) {
            $language_id = (int) $this->config->get('config_language_id');
        }
        $sql = "SELECT p.product_id, p.model, p.sku, pd.name, pt.product_type FROM `" . DB_PREFIX . "product` p
                JOIN `" . DB_PREFIX . "product_description` pd ON(pd.product_id=p.product_id AND pd.language_id={$language_id})
                JOIN `" . DB_PREFIX . $this->_table_product_type . "` pt ON(pt.product_id=p.product_id)";
        $query = $this->db->query($sql);
        return $query->rows;
    }
    
    public function getProductsList($language_id = 0) {
        $language_id = (int) $language_id;
        if (!$language_id) {
            $language_id = (int) $this->config->get('config_language_id');
        }
        $sql = "SELECT p.product_id, pd.name FROM `" . DB_PREFIX . "product` p
                JOIN `" . DB_PREFIX . "product_description` pd ON(pd.product_id=p.product_id AND pd.language_id={$language_id})
                ORDER BY pd.name";
        $query = $this->db->query($sql);
        return $query->rows;
    }
    
    public function getToken() {
        if (isset($this->session->data['token'])) {
            return 'token=' . $this->session->data['token'];
        }
        if (isset($this->session->data['user_token'])) {
            return 'user_token=' . $this->session->data['user_token'];
        }
        return '';
    }

    public function getInvoiceData($order_id) {
        $sql = sprintf('SELECT * FROM `' . DB_PREFIX . $this->_table_invoice . '` WHERE `order_id`=%d', $order_id);
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function setInvoiceData($order_id, array $data) {
        $sql = sprintf('REPLACE INTO `' . DB_PREFIX . $this->_table_invoice . '` (number, series_name, link, order_id) VALUES(%d, "%s", "%s", %d)',
            $data['number'], $this->db->escape($data['seriesName']), $this->db->escape($data['link']), $order_id);
        $this->db->query($sql);
    }

    public function isLastInvoice($invoice) {
        $sql = sprintf("SELECT MAX(number) AS max_number FROM `%s` WHERE `series_name`='%s'",
            DB_PREFIX . $this->_table_invoice, $invoice['series_name']);
        $query = $this->db->query($sql);
        return (int) $query->row['max_number'] === (int) $invoice['number'];
    }

    public function getPriceWithVat($price, $vatPercentage = 0) {
        return (float) number_format($price * (100 + $vatPercentage) / 100, 2, '.', '');
    }
    
    private function _checkTableType() {
        $sql = "SHOW TABLES LIKE '" . DB_PREFIX . $this->_table_product_type . "'";
        $query = $this->db->query($sql);
        if ($query->num_rows === 0) {
            $sql = "CREATE TABLE `" . DB_PREFIX . $this->_table_product_type . "` (
              `product_id` int(11) NOT NULL,
              `product_type` varchar(50) NOT NULL DEFAULT 'Marfa',
              PRIMARY KEY (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            $this->db->query($sql);
        }
    }

    private function _checkTableInvoice() {
        $sql = "SHOW TABLES LIKE '" . DB_PREFIX . $this->_table_invoice . "'";
        $query = $this->db->query($sql);
        if ($query->num_rows === 0) {
            $sql = "CREATE TABLE `" . DB_PREFIX . $this->_table_invoice . "` (
              `order_id` int(11) NOT NULL,
              `series_name` varchar(15) NOT NULL DEFAULT '',
              `number` int(11) NOT NULL,
              `link` varchar(255) NOT NULL,
              PRIMARY KEY (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            $this->db->query($sql);
        }
    }

    private function _addCronTab() {
        $cronCode = 'oblio_stock';
        $this->load->model('setting/cron');
        $cron = $this->model_setting_cron->getCronByCode($cronCode);

        if (empty($cron)) {
            $this->model_setting_cron->addCron($cronCode, '', 'hour', 'extension/oblio/module/oblio.cron', '0');
        }
    }
}
