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

class Fontis_Info_Model_Severities
{
    public function toOptionArray()
    {
        $helper = Mage::helper('fontis_info');
        return array(
            array(
                'value' => Mage_AdminNotification_Model_Inbox::SEVERITY_CRITICAL,
                'label' => $helper->__('Critical')
            ),
            array(
                'value' => Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR,
                'label' => $helper->__('Major')
            ),
            array(
                'value' => Mage_AdminNotification_Model_Inbox::SEVERITY_MINOR,
                'label' => $helper->__('Minor')
            ),
            array(
                'value' => Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE,
                'label' => $helper->__('Notice')
            )
        );
    }
}
