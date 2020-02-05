<?php
/**
* 2007-2015 PrestaShop.
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Etseofriendlyurl extends Module
{
    private $override_rules = array();

    private $request_uri = null;

    public function __construct()
    {
        $this->name = 'etseofriendlyurl';
        $this->tab = 'front_office_features';
        $this->version = '1.3.1';
        $this->author = 'Express Tech';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = 'de08ab2dfa4f778722b7c5952e610189';
        parent::__construct();

        $this->displayName = $this->l('SEO Friendly URLs');
        $this->description = $this->l('Removes ID from Prestashop URLs to make them SEO friendly');
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('moduleRoutes') || !$this->registerHook('actionDispatcher')) {
            return false;
        }

        Configuration::updateValue('PS_SFUV_CATEGORY', 1);
        Configuration::updateValue('PS_SFUV_CATEGORY_URLKEY', 'category');
        Configuration::updateValue('PS_SFUV_PRODUCT', 1);
        Configuration::updateValue('PS_SFUV_PRODUCT_DISABLE_EAN13', false);
        Configuration::updateValue('PS_SFUV_PRODUCT_DISABLE_CATEGORY', false);
        Configuration::updateValue('PS_SFUV_CMS', 1);
        Configuration::updateValue('PS_SFUV_SUPPLIER', 1);
        Configuration::updateValue('PS_SFUV_MANUFACTURER', 1);
        $this->clearCache();

        return true;
    }

    public function clearCache()
    {

        //1.7
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->_clearCache('ps_mainmenu.tpl');
            // $this->_clearCache('ps_.tpl');
        } else {
            $this->_clearCache('blocktopmenu.tpl');
            $this->_clearCache('blockcms.tpl');
        }
    }

    public function uninstall()
    {
        $this->clearCache();

        return parent::uninstall();
    }

    public function enable($force_all = false)
    {
        $this->clearCache();

        return parent::enable($force_all);
    }

    public function disable($force_all = false)
    {
        $this->clearCache();
        parent::disable($force_all);
    }

    public function hookActionDispatcher($params)
    {
        // echo $params['controller_class'];

        // var_dump($params);

        $controller = $params['controller_class'];

        $id_lang = (int) Context::getContext()->language->id;

        if ($controller == 'ProductController' && Configuration::get('PS_SFUV_PRODUCT')) {
            $param_search = Tools::getValue('rewrite');
            //check if EAN13 was appended to the rewrite parameter.
            $query = 'pl.`link_rewrite` = \''.pSQL($param_search).'\'';
            if (preg_match('/-[0-9]*$/', $param_search, $m) && is_array($m) && count($m) > 0) {
                $param_search_new = str_replace($m[0], '', $param_search);
                $_GET['rewrite'] = '';
                $query = '(pl.`link_rewrite` = \''.pSQL($param_search).'\' or pl.`link_rewrite` = \''.pSQL($param_search_new).'\')';
            }
            // var_dump($param_search);exit;
            $id_product = Db::getInstance()->getValue('
                SELECT pl.`id_product`
                FROM `'._DB_PREFIX_.'product_lang` pl
                WHERE `id_lang` = '.(int) $id_lang.'
                '.Shop::addSqlRestrictionOnLang('pl').'
                AND '.$query);
            if ($id_product > 0) {
                $_GET['id_product'] = $id_product;
            }
        }

        if ($controller == 'CategoryController' && Configuration::get('PS_SFUV_CATEGORY')) {
            $param_search = Tools::getValue('rewrite');

            $id_category = Db::getInstance()->getValue('
                SELECT cl.`id_category`
                FROM `'._DB_PREFIX_.'category_lang` cl
                WHERE `id_lang` = '.(int) $id_lang.'
                '.Shop::addSqlRestrictionOnLang('cl').'
                AND cl.`link_rewrite` = \''.pSQL($param_search).'\'');
            if ($id_category > 0) {
                $_GET['id_category'] = $id_category;
            }
        }

        if ($controller == 'CmsController' && Configuration::get('PS_SFUV_CMS')) {
            if (Tools::getValue('rewrite_cms_cat', false)) {
                $param_search = Tools::getValue('rewrite_cms_cat');
                $id_cms_category = Db::getInstance()->getValue('
                    SELECT pl.`id_cms_category`
                    FROM `'._DB_PREFIX_.'cms_category_lang` pl
                    WHERE `id_lang` = '.(int) $id_lang.'
                    '.Shop::addSqlRestrictionOnLang('pl').'
                    AND pl.`link_rewrite` = \''.pSQL($param_search).'\'');
                if ($id_cms_category > 0) {
                    $_GET['id_cms_category'] = $id_cms_category;
                }
            } else {
                $param_search = Tools::getValue('rewrite_cms_id');

                $id_cms = Db::getInstance()->getValue('
                    SELECT pl.`id_cms`
                    FROM `'._DB_PREFIX_.'cms_lang` pl
                    WHERE `id_lang` = '.(int) $id_lang.'
                    '.Shop::addSqlRestrictionOnLang('pl').'
                    AND pl.`link_rewrite` = \''.pSQL($param_search).'\'');
                if ($id_cms > 0) {
                    $_GET['id_cms'] = $id_cms;
                }
            }
        }

        if ($controller == 'SupplierController' && Configuration::get('PS_SFUV_SUPPLIER')) {
            $param_search = Tools::getValue('rewrite');
            if ($param_search) {
                $param_search_nodash = str_replace('-', ' ', $param_search);
            }
            $id_supplier = Db::getInstance()->getValue('SELECT pl.`id_supplier` FROM `'._DB_PREFIX_.'supplier` pl WHERE pl.`name` LIKE \''.pSQL($param_search).'\' or pl.`name` LIKE \''.pSQL($param_search_nodash).'\'');
            if ($id_supplier > 0) {
                $_GET['id_supplier'] = $id_supplier;
            }
        }
        if ($controller == 'ManufacturerController' && Configuration::get('PS_SFUV_MANUFACTURER')) {
            $param_search = Tools::getValue('rewrite');
            if ($param_search) {
                $param_search_nodash = str_replace('-', ' ', $param_search);
            }
            
            $id_manufacturer = Db::getInstance()->getValue('SELECT pl.`id_manufacturer`                     FROM `'._DB_PREFIX_.'manufacturer` pl   WHERE pl.`name` LIKE \''.pSQL($param_search).'\' or pl.`name` LIKE \''.pSQL($param_search_nodash).'\' or REPLACE(pl.`name`,\'&\', \'\') LIKE \''.pSQL($param_search).'\'');
            if ($id_manufacturer > 0) {
                $_GET['id_manufacturer'] = $id_manufacturer;
            }
        }
    }

    public function hookModuleRoutes($params)
    {
        $this->override_rules = array();

        if (Configuration::get('PS_SFUV_SUPPLIER')) {
            $this->override_rules['supplier_rule'] = array(
                'controller' => 'supplier',
                'rule' => 'supplier/{rewrite}',
                'keywords' => array(
                    'id' => array('regexp' => '[0-9]+', 'param' => 'id_supplier'),
                    'rewrite' => array('regexp' => '[_a-zA-Z0-9\pL\pS-]+', 'param' => 'rewrite'),
                    'meta_keywords' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                    'meta_title' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                ),
                'params' => array(),
            );
        }

        if (Configuration::get('PS_SFUV_MANUFACTURER')) {
            $this->override_rules['manufacturer_rule'] = array(
                'controller' => 'manufacturer',
                'rule' => 'manufacturer/{rewrite}',
                'keywords' => array(
                    'id' => array('regexp' => '[0-9]+', 'param' => 'id_manufacturer'),
                    'rewrite' => array('regexp' => '[_a-zA-Z0-9\pL\pS-]+', 'param' => 'rewrite'),
                    'meta_keywords' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                    'meta_title' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                ),
                'params' => array(),
            );
        }
        if (Configuration::get('PS_SFUV_CMS')) {
            $this->override_rules['cms_rule'] = array(
                'controller' => 'cms',
                'rule' => 'content/{rewrite}',
                'keywords' => array(
                    'id' => array('regexp' => '[0-9]+', 'param' => 'id_cms'),
                    'rewrite' => array('regexp' => '[_a-zA-Z0-9\pL\pS-]*', 'param' => 'rewrite_cms_id'),
                    'meta_keywords' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                    'meta_title' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                ),
                'params' => array(),
            );
        }

        if (Configuration::get('PS_SFUV_CMS')) {
            $this->override_rules['cms_category_rule'] = array(
                'controller' => 'cms',
                'rule' => 'content/category/{rewrite}',
                'keywords' => array(
                    'id' => array('regexp' => '[0-9]+', 'param' => 'id_cms_category'),
                    'rewrite' => array('regexp' => '[_a-zA-Z0-9\pL\pS-]*', 'param' => 'rewrite_cms_cat'),
                    'meta_keywords' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                    'meta_title' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                ),
                'params' => array(),
            );
        }

        if (Configuration::get('PS_SFUV_PRODUCT')) {

            $eanRule = Configuration::get('PS_SFUV_PRODUCT_DISABLE_EAN13') ? '' : '{-:ean13}'; 
            $catRule = Configuration::get('PS_SFUV_PRODUCT_DISABLE_CATEGORY') ? '' : '{category:/}'; 

            if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                $this->override_rules['product_rule'] = array(
                    'controller' => 'product',
                    'rule' => $catRule.'{id_product_attribute:-}{rewrite}'.$eanRule.'.html',
                    'keywords' => array(
                        'id' => array('regexp' => '[0-9]+', 'param' => 'id_product'),
                        'id_product_attribute' => array('regexp' => '[0-9]+', 'param' => 'id_product_attribute'),
                        'rewrite' => array('regexp' => '[_a-zA-Z0-9\pL\pS-]*', 'param' => 'rewrite'),
                        'ean13' => array('regexp' => '[0-9\pL]*'),
                        'category' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'categories' => array('regexp' => '[/_a-zA-Z0-9-\pL]*'),
                        'reference' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'meta_keywords' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'meta_title' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'manufacturer' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'supplier' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'price' => array('regexp' => '[0-9\.,]*'),
                        'tags' => array('regexp' => '[a-zA-Z0-9-\pL]*'),
                    ),
                    'params' => array(),
                );
            } else {
                $this->override_rules['product_rule'] = array(
                    'controller' => 'product',
                    'rule' => $catRule.'{id_product_attribute:-}{rewrite}'.$eanRule.'.html',
                    'keywords' => array(
                        'id' => array('regexp' => '[0-9]+', 'param' => 'id_product'),
                        'rewrite' => array('regexp' => '[_a-zA-Z0-9\pL\pS-]*', 'param' => 'rewrite'),
                        'ean13' => array('regexp' => '[0-9\pL]*', 'param' => 'ean13'),
                        'category' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'categories' => array('regexp' => '[/_a-zA-Z0-9-\pL]*'),
                        'reference' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'meta_keywords' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'meta_title' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'manufacturer' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'supplier' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                        'price' => array('regexp' => '[0-9\.,]*'),
                        'tags' => array('regexp' => '[a-zA-Z0-9-\pL]*'),
                    ),
                    'params' => array(),
                );
            }
        }

        $category_urlkey = trim(Configuration::get('PS_SFUV_CATEGORY_URLKEY'));

        if ($category_urlkey == '') {
            $category_urlkey = 'category';
        }

        if (Configuration::get('PS_SFUV_CATEGORY')) {
            $this->override_rules['category_rule'] = array(
                'controller' => 'category',
                'rule' => $category_urlkey.'/{rewrite}',
                'keywords' => array(
                    'id' => array('regexp' => '[0-9]+', 'param' => 'id_category'),
                    'rewrite' => array('regexp' => '[_a-zA-Z0-9\pL\pS-]*', 'param' => 'rewrite'),
                    'meta_keywords' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                    'meta_title' => array('regexp' => '[_a-zA-Z0-9-\pL]*'),
                ),
                'params' => array(),
            );
        }

        return $this->override_rules;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Category'),
                        'name' => 'PS_SFUV_CATEGORY',
                        'is_bool' => true,
                        'desc' => $this->l('Remove ID from Category URLs'),
                        'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled'),
                                ),
                            ),
                        ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Category Keyword'),
                        'name' => 'PS_SFUV_CATEGORY_URLKEY',
                        'is_bool' => true,
                        'desc' => $this->l('Keyword used to generate Category URLs (/category/tshirts).')
                        ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Product'),
                        'name' => 'PS_SFUV_PRODUCT',
                        'is_bool' => true,
                        'desc' => $this->l('Remove ID from Product URLs'),
                        'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled'),
                                ),
                            ),
                        ),
                    array(
                        'type' => 'switch',
                        'label' => '&nbsp;'.'&nbsp;'.'&nbsp;'.$this->l('Remove category from product URLs'),
                        'name' => 'PS_SFUV_PRODUCT_DISABLE_CATEGORY',
                        'is_bool' => true,
                        'desc' => $this->l('Remove category from Product URLs (make sure all product\'s friendly URLs are unique)'),
                        'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled'),
                                ),
                            ),
                        ),
                    array(
                        'type' => 'switch',
                        'label' => '&nbsp;'.'&nbsp;'.'&nbsp;'.$this->l('Remove product EAN13'),
                        'name' => 'PS_SFUV_PRODUCT_DISABLE_EAN13',
                        'is_bool' => true,
                        'desc' => $this->l('Remove EAN13 from Product URLs (make sure all product\'s friendly URLs are unique)'),
                        'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled'),
                                ),
                            ),
                        ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('CMS'),
                        'name' => 'PS_SFUV_CMS',
                        'is_bool' => true,
                        'desc' => $this->l('Remove ID from CMS URLs'),
                        'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled'),
                                ),
                            ),
                        ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Supplier'),
                        'name' => 'PS_SFUV_SUPPLIER',
                        'is_bool' => true,
                        'desc' => $this->l('Remove ID from Supplier URLs'),
                        'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled'),
                                ),
                            ),
                        ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Manufacturer'),
                        'name' => 'PS_SFUV_MANUFACTURER',
                        'is_bool' => true,
                        'desc' => $this->l('Remove ID from Manufacturer URLs'),
                        'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled'),
                                ),
                            ),
                        ),

                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSFUV';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab
        .'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'PS_SFUV_CATEGORY' => (bool) Tools::getValue('PS_SFUV_CATEGORY', Configuration::get('PS_SFUV_CATEGORY')),
            'PS_SFUV_CATEGORY_URLKEY' => Tools::getValue('PS_SFUV_CATEGORY_URLKEY', Configuration::get('PS_SFUV_CATEGORY_URLKEY')),
            'PS_SFUV_PRODUCT' => (bool) Tools::getValue('PS_SFUV_PRODUCT', Configuration::get('PS_SFUV_PRODUCT')),
            'PS_SFUV_PRODUCT_DISABLE_EAN13' => (bool) Tools::getValue('PS_SFUV_PRODUCT_DISABLE_EAN13', Configuration::get('PS_SFUV_PRODUCT_DISABLE_EAN13')),
            'PS_SFUV_PRODUCT_DISABLE_CATEGORY' => (bool) Tools::getValue('PS_SFUV_PRODUCT_DISABLE_CATEGORY', Configuration::get('PS_SFUV_PRODUCT_DISABLE_CATEGORY')),
            'PS_SFUV_CMS' => (int) Tools::getValue('PS_SFUV_CMS', Configuration::get('PS_SFUV_CMS')),
            'PS_SFUV_SUPPLIER' => (int) Tools::getValue('PS_SFUV_SUPPLIER', Configuration::get('PS_SFUV_SUPPLIER')),
            'PS_SFUV_MANUFACTURER' => (int) Tools::getValue('PS_SFUV_MANUFACTURER', Configuration::get('PS_SFUV_MANUFACTURER')),
        );
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitSFUV')) {
            Configuration::updateValue('PS_SFUV_CATEGORY', (int) (Tools::getValue('PS_SFUV_CATEGORY')));
            Configuration::updateValue('PS_SFUV_CATEGORY_URLKEY', Tools::getValue('PS_SFUV_CATEGORY_URLKEY'));
            Configuration::updateValue('PS_SFUV_PRODUCT', (int) (Tools::getValue('PS_SFUV_PRODUCT')));

            $PS_SFUV_PRODUCT_DISABLE_EAN13 = (int) (Tools::getValue('PS_SFUV_PRODUCT')) ? (int) (Tools::getValue('PS_SFUV_PRODUCT_DISABLE_EAN13')) : 0;
            $PS_SFUV_PRODUCT_DISABLE_CATEGORY = (int) (Tools::getValue('PS_SFUV_PRODUCT')) ? (int) (Tools::getValue('PS_SFUV_PRODUCT_DISABLE_CATEGORY')) : 0;
            // echo $PS_SFUV_PRODUCT_DISABLE_EAN13;exit;
            Configuration::updateValue('PS_SFUV_PRODUCT_DISABLE_EAN13', $PS_SFUV_PRODUCT_DISABLE_EAN13);
            Configuration::updateValue('PS_SFUV_PRODUCT_DISABLE_CATEGORY', $PS_SFUV_PRODUCT_DISABLE_CATEGORY);
            
            Configuration::updateValue('PS_SFUV_CMS', (int) (Tools::getValue('PS_SFUV_CMS')));
            Configuration::updateValue('PS_SFUV_SUPPLIER', (int) (Tools::getValue('PS_SFUV_SUPPLIER')));
            Configuration::updateValue('PS_SFUV_MANUFACTURER', (int) (Tools::getValue('PS_SFUV_MANUFACTURER')));
            $this->clearCache();
            $output .= $this->displayConfirmation($this->l('Settings Saved. Clear your cache if you get inconsistent results.'));
        }

        return $output.$this->renderForm();
    }
}
