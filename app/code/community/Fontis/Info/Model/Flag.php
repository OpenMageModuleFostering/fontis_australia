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

class Fontis_Info_Model_Flag extends Mage_Core_Model_Flag
{
    const FLAGCODE_PREFIX = 'fontis_info_latest_version';

    public function setExtension($extension)
    {
        $this->_flagCode = self::FLAGCODE_PREFIX . '_' . $extension;
        return $this;
    }
}
