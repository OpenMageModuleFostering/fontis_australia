<?php
/**
 * Fontis Info Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Fontis
 * @package    Fontis_Info
 * @author     Jeremy Champion
 * @author     Matthew Gamble
 * @copyright  Copyright (c) 2014 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fontis_Info_Model_Info
{
    /**
     * @param array $data Array of each installed Fontis extensions version
     * @return string
     * @throws Exception
     */
    public function request($data)
    {
        $client = new Varien_Http_Client(Mage::helper('fontis_info')->getInfoUrl(), array('keepalive' => true));
        $client->setParameterPost('data', $data);
        // If we accept gzip encoding and the response isn't actually chunked, Zend_Http_Response will throw
        // an exception. Unfortunately, this means we can't use gzip encoded responses at this time.
        $client->setHeaders('Accept-encoding', 'identity');

        $response = $client->request(Varien_Http_Client::POST);

        if ($response->isError()) {
            // if the request fails, throw an exception
            throw new Exception('Error getting info data: ' . $response->getStatus() . ' ' . $response->getMessage());
        }

        return $response->getBody();
    }

    /**
     * @return array
     */
    public function getCoreVersionInfo()
    {
        $core = array();
        $core['store_id'] = $this->getStoreId();
        $core['mage_version'] = Mage::getVersion();
        if (method_exists('Mage', 'getEdition')) {
            $core['mage_edition'] = Mage::getEdition();
        }
        return $core;
    }

    /**
     * Check if we can and should run a check for new messages
     *
     * Will be run no more often than once every 24 hours
     *
     * @return bool
     */
    public function canRun()
    {
        // get the last run time from the cache
        $lastRun = Mage::app()->loadCache('fontis_info_lastcheck');

        if ($lastRun) {
            $now = time();
            // if the difference between now and the last run is at least 24 hours, we can run
            if ($now - $lastRun < (24 * 60 * 60)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the unique ID for this install
     *
     * This ID is generated purely using the uniqid method, which is based on the current timestamp. This means that
     * there's no way to work back from the id to find the actual site.
     *
     * @return string
     */
    public function getStoreId()
    {
        // see if we've already got an ID stored in the config
        /** @var $storeId Mage_Core_Model_Flag */
        $storeId = Mage::getModel('core/flag', array('flag_code' => 'fontis_info_store_id'))->loadSelf();

        if (!$storeId->getFlagData()) {
            // if not, generate a new one
            $id = Mage::helper('fontis_info')->createUUID();

            // and save it into the database
            $storeId->setFlagData($id);
            $storeId->save();
        }

        return $storeId->getFlagData();
    }

    /**
     * Run the update to check for new messages.
     *
     * This is usually called as a Magento observer method, but it's safe to call this at any time.
     *
     * Note that calling this function does not guarantee that the message update will be run. This method will only
     * run the update if the canRun() method returns true - which will not happen any more often than once every 24
     * hours.
     *
     * @param null $observer Observer object passed in through Magento's event dispatch system. Unused.
     */
    public function update($observer = null)
    {
        // check that we haven't run too recently
        if (!$this->canRun()) {
            return;
        }

        try {
            // prepare a list of versions, and make the request.
            $postData = array();
            $postData['extensions'] = Mage::helper('fontis_info')->getCurrentVersions();
            $postData['core'] = $this->getCoreVersionInfo();

            $transferObject = new Varien_Object;
            Mage::dispatchEvent('fontis_info_update_request', array('object' => $transferObject));
            $postData['extra'] = $transferObject->getData();

            /** @var $coreHelper Mage_Core_Helper_Data */
            $coreHelper = Mage::helper('core');
            $updates = $coreHelper->jsonDecode($this->request($coreHelper->jsonEncode($postData)), true);

            if (isset($updates['notices'])) {
                $this->parseNotifications($updates['notices']);
            }
            if (isset($updates['latest_versions'])) {
                $this->updateLatestVersions($updates['latest_versions']);
            }

            // update the last run time
            Mage::app()->saveCache(time(), 'fontis_info_lastcheck');
        } catch (Exception $e) {
            // if something goes wrong, just log the error message
            Mage::log($e->__toString(), null, 'fontis_info.log');
        }
    }

    /**
     * Update list of Fontis extensions and their latest version numbers
     *
     * @param array $updates Latest version numbers for Fontis extensions
     */
    protected function updateLatestVersions(array $updates = array())
    {
        foreach ($updates as $code => $version) {
            $extension = Mage::getModel('fontis_info/flag')->setExtension($code)->loadSelf();
            $extension->setFlagData($version)->save();
        }
    }

    /**
     * Parse all notifications received from Fontis and add the relevant ones
     * to the Messages Inbox.
     *
     * @param array $updates Notices
     */
    protected function parseNotifications(array $updates = array())
    {
        $messages = array();
        $helper = Mage::helper('fontis_info');
        $versions = $helper->getCurrentVersions();
        foreach ($updates as $message) {
            if (
                !isset($message['severity']) ||
                $message['severity'] > $helper->getMinimumSeverityLevel()
            ) {
                continue;
            }

            // find which extension this message is for, and then remove it so that the SQL insert doesn't fail
            $extension = $message['extension'];
            unset($message['extension']);

            // for each message we get returned, check that we have the relevant extension installed, or that the
            // message is one of the global types
            if ($extension == 'Core' || $extension == 'Announcement' || array_key_exists($extension, $versions)) {
                $messages[] = $message;
            }
        }

        // use the core method to add the messages to the inbox. This includes checking for duplicate entries
        Mage::getModel('adminnotification/inbox')->parse($messages);
    }
}

