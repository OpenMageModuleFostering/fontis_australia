<?php
/**
 * Fontis Australia Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com and you will be sent a copy immediately.
 *
 * @category   Fontis
 * @package    Fontis_Australia
 * @author     Tom Greenaway
 * @copyright  Copyright (c) 2009 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

function addProductXmlCallback($args)
{
    $product = $args['product'];
    $product->setData($args['row']);
    addProductXml($product);
}

function addProductXml($product)
{
    $product_id = $product->getId();
    $store_id = Fontis_Australia_Model_MyShopping_Cron::$store->getId();

    $data = exec("php " . Mage::getBaseDir() . "/app/code/community/Fontis/Australia/Model/Myshopping/Child.php " . Mage::getBaseDir() . " " . $product_id . " " . $store_id);
    $array = unserialize($data);

    $product_node = Fontis_Australia_Model_Myshopping_Cron::$root_node->addChild('product');

    Mage::log(var_export($array, true));

    foreach($array as $key => $val) {
        $product_node->addChild($key, $val);
    }
}

class Fontis_Australia_Model_MyShopping_Cron
{
    public static $doc;
    public static $root_node;
    public static $store;

    protected function _construct()
    {
    }

    protected function getPath()
    {
        $path = "";
        $config_path = Mage::getStoreConfig('fontis_feeds/myshoppingfeed/output');

        if (substr($config_path, 0, 1) == "/") {
            $path = $config_path . '/';
        } else {
            $path = Mage::getBaseDir() . '/' . $config_path . '/';
        }

        return str_replace('//', '/', $path);
    }

    public static function update()
    {
        session_start();

        Mage::log('Fontis/Australia_Model_MyShopping_Cron: entered update function');
        if (Mage::getStoreConfig('fontis_feeds/myshoppingfeed/active')) {
            $io = new Varien_Io_File();
            $io->setAllowCreateFolders(true);

            $io->open(array('path' => self::getPath()));

            foreach(Mage::app()->getStores() as $store) {
                Mage::log('for each store');
                $clean_store_name = str_replace('+', '-', strtolower(urlencode($store->getName())));
                $products_result = self::getProductsXml($store);

                // Write the entire products xml file:
                $io->write($clean_store_name . '-products.xml', $products_result['xml']);
                Mage::log('successful write?');
            }

            $io->close();
        }
    }

    public function nonstatic()
    {
        self::update();
    }

    public function getProductsXml($store)
    {
        Mage::log('new getproductsxml');
        Fontis_Australia_Model_MyShopping_Cron::$store = $store;

        $result = array();

        $product = Mage::getModel('catalog/product');
        $products = $product->getCollection();
        $products->setStoreId($store);
        $products->addStoreFilter();
        $products->addAttributeToSelect('*');

        $attributes_select_array = array('name', 'price', 'image', 'status');
        $linkedAttributes = @unserialize(Mage::getStoreConfig('fontis_feeds/myshoppingfeed/m_to_xml_attributes', $store->getId()));
        if(!empty($linkedAttributes))
        {
            foreach($linkedAttributes as $la)
            {
                if (strpos($la['magento'], 'FONTIS') === false) {
                    $attributes_select_array[] = $la['magento'];
                }
            }
        }

        $products->addAttributeToSelect($attributes_select_array, 'left');
        $products->addAttributeToFilter('type_id', 'simple');

        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($products);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($products);

        $storeUrl = $store->getBaseUrl();
        $shopName = $store->getName();
        $date = date("d-m-Y", Mage::getModel('core/date')->timestamp(time()));
        $time = date("h:i:s", Mage::getModel('core/date')->timestamp(time()));

        self::$doc = new SimpleXMLElement('<productset></productset>');
        self::$root_node = self::$doc;

        Mage::log('about to walk');
        Mage::getSingleton('core/resource_iterator')->walk($products->getSelect(), array('addProductXmlCallback'), array('product' => $product));
        Mage::log('walked');

        $result['xml'] = self::$doc->asXml();
        return $result;
    }
}
