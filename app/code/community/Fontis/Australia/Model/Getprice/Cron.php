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
 * @copyright  Copyright (c) 2008 Fontis Pty. Ltd. (http://www.fontis.com.au)
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
    $store_id = Fontis_Australia_Model_GetPrice_Cron::$store->getId();

    $data = exec("php " . Mage::getBaseDir() . "/app/code/community/Fontis/Australia/Model/Getprice/Child.php " . Mage::getBaseDir() . " " . $product_id . " " . $store_id);
    $array = unserialize($data);

    $product_node = Fontis_Australia_Model_GetPrice_Cron::$root_node->addChild('product');

    foreach($array as $key => $val) {
        if ($key == "num") {
            $product_node->addAttribute($key, $val);
        } else {
            $product_node->addChild($key, $val);
        }
    }
}

class Fontis_Australia_Model_GetPrice_Cron
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
        $config_path = Mage::getStoreConfig('fontis_feeds/getpricefeed/output');

        if (substr($config_path, 0, 1) == "/") {
            $path = $config_path . '/';
        } else {
            $path = Mage::getBaseDir() . '/' . $config_path . '/';
        }

        return str_replace('//', '/', $path);
    }

    public function nonstatic()
    {
        self::update();
    }

    public static function update()
    {
        Mage::log('Fontis/Australia_Model_Getprice_Cron: entered update function');
        session_start();

        if (Mage::getStoreConfig('fontis_feeds/getpricefeed/active')) {
            $io = new Varien_Io_File();
            $io->setAllowCreateFolders(true);

            $io->open(array('path' => self::getPath()));

            foreach(Mage::app()->getStores() as $store) {
                $clean_store_name = str_replace('+', '-', strtolower(urlencode($store->getName())));

                $categories_result = self::getCategoriesXml($store);
                $products_result = self::getProductsXml($store);

                // Write the leaf categories xml file:
                $io->write($clean_store_name . '-categories.xml', $categories_result['xml']);

                // Write the entire products xml file:
                $io->write($clean_store_name . '-products.xml', $products_result['xml']);

                // Write for each leaf category, their products xml file:
                foreach($categories_result['link_ids'] as $link_id) {
                    $subcategory_products_result = self::getProductsXml($store, $link_id);
                    $io->write($clean_store_name . '-products-'.$link_id.'.xml', $subcategory_products_result['xml']);
                }
            }

            $io->close();
        }
    }

    public function getCategoriesXml($store)
    {
        $result = array();
        $categories = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($store->getId())
            ->addAttributeToFilter('is_active', 1);

        $categories->load()->getItems();

        $full_categories = array();

        foreach($categories as $category) {
            $id = $category->getId();
            $category = Mage::getModel('catalog/category')->load($id);

            $children = $category->getAllChildren(true);
            if (count($children) <= 1) {
                $full_categories[] = $category;
            }
        }

        $storeUrl = $store->getBaseUrl();
        $shopName = $store->getName();
        $date = date("d-m-Y", Mage::getModel('core/date')->timestamp(time()));
        $time = date("h:i:s", Mage::getModel('core/date')->timestamp(time()));

        $doc = new SimpleXMLElement('<store url="' . $storeUrl. '" date="'.$date.'" time="'.$time.'" name="' . $shopName . '"></store>');

        foreach($full_categories as $category) {
            $category_node = $doc->addChild('cat');

            $title_node = $category_node->addChild('name');
            $title_node[0] = $category->getName();

            $link_node = $category_node->addChild('link');
            $link_node[0] = Mage::getStoreConfig('web/unsecure/base_url') . 'products-' . $category->getId() . '.xml';

            $result['link_ids'][] = $category->getId();
        }

        $result['xml'] = $doc->asXml();
        return $result;
    }

    public function getProductsXml($store, $cat_id = -1)
    {
        Fontis_Australia_Model_GetPrice_Cron::$store = $store;
        $result = array();

        $product = Mage::getModel('catalog/product');
        $products = $product->getCollection();
        $products->setStoreId($store);
        $products->addStoreFilter();
        $products->addAttributeToSelect('*');
        $products->addAttributeToSelect(array('name', 'price', 'image', 'status', 'manufacturer'), 'left');
        $products->addAttributeToFilter('type_id', 'simple');

        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($products);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($products);

        if ($cat_id != -1) {
            $products->getSelect()->where("e.entity_id IN (
                SELECT product_id FROM catalog_category_product WHERE category_id = ".$cat_id."
                )");
        }

        $storeUrl = $store->getBaseUrl();
        $shopName = $store->getName();
        $date = date("d-m-Y", Mage::getModel('core/date')->timestamp(time()));
        $time = date("h:i:s", Mage::getModel('core/date')->timestamp(time()));

        self::$doc = new SimpleXMLElement('<store url="' . $storeUrl. '" date="'.$date.'" time="'.$time.'" name="' . $shopName . '"></store>');
        self::$root_node = self::$doc->addChild('products');

        Mage::getSingleton('core/resource_iterator')->walk($products->getSelect(), array('addProductXmlCallback'), array('product' => $product));

        $result['xml'] = self::$doc->asXml();
        return $result;
    }
}
