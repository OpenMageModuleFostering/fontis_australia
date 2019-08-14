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

class Fontis_Info_Block_Extensions extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    /**
     * Render a table displaying every Fontis extension installed.
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);

        /** @var $helper Fontis_Info_Helper_Data */
        $helper = Mage::helper('fontis_info');
        $currentVersions = $helper->getCurrentVersions();
        $html .= '<table class="grid">';
        $html .= '<tbody>';
        $html .= '<tr class="headings"><th>' . $this->__('Extension') . '</th><th>' . $this->__('Installed Version') . '</th><th>' . $this->__('Latest Version') . '</th></tr>';
        foreach ($currentVersions as $code => $version) {
            $latestVersion = $helper->getLatestVersion($code);
            if (!$latestVersion) {
                $latestVersion = '-';
            }
            $html .= '<tr><td>' . $code . '</td><td>' . $version . '</td><td>' . $latestVersion . '</td></tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';

        $html .= $this->_getFooterHtml($element);

        return $html;
    }
}
