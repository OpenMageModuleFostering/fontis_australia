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

class Fontis_Info_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * URL that the messages feed can be requested through
     */
    const URL = 'https://www.fontis.com.au/updates';

    /**
     * An array of all installed Fontis extensions and their version numbers.
     *
     * @var array
     */
    protected $versions = null;

    /**
     * Return the messages feed URL
     *
     * @return string
     */
    public function getInfoUrl()
    {
        return self::URL;
    }

    /**
     * Returns the minimum notification security level allowed.
     *
     * @return int
     */
    public function getMinimumSeverityLevel()
    {
        $severityLevel = Mage::getStoreConfig('fontis_info/settings/minimum_severity_level');
        if ($severityLevel) {
            return $severityLevel;
        }
        return Mage_AdminNotification_Model_Inbox::SEVERITY_CRITICAL;
    }

    /**
     * Gets the latest known version of the given extension.
     *
     * @param string $code Extension name
     * @return string
     */
    public function getLatestVersion($code)
    {
        $extension = Mage::getModel('fontis_info/flag')->setExtension($code)->loadSelf();
        return $extension->getFlagData();
    }

    /**
     * Returns an array of all installed Fontis extensions and their version numbers.
     *
     * @param string $module
     * @return array
     */
    public function getCurrentVersions($module = null)
    {
        if ($this->versions === null) {
            $modules = (array) Mage::getConfig()->getNode('modules');
            // look through each installed extension
            $this->versions = array();
            foreach ($modules as $code => $data) {
                $data = (array) $data;
                // does it start with Fontis? If so, that means we care about it.
                if (preg_match('/^fontis/i', $code)) {
                    $this->versions[$code] = isset($data['version']) ? $data['version'] : 'N/A';
                }
            }
        }
        if (isset($this->versions[$module])) {
            return $this->versions[$module];
        } else {
            return $this->versions;
        }
    }

    /**
     * Generate version 4 UUID
     *
     * https://github.com/ramsey/uuid
     *
     * @return string
     */
    public function createUUID()
    {
        $bytes = '';
        $length = 16;
        $version = 4;

        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(16);
        } else {
            for ($i = 1; $i <= $length; $i++) {
                $bytes = chr(mt_rand(0, 256)) . $bytes;
            }
        }

        $hex = bin2hex($bytes);

        $timeHi = hexdec(substr($hex, 12, 4)) & 0x0fff;
        $timeHi &= ~(0xf000);
        $timeHi |= $version << 12;

        // Set the variant to RFC 4122
        $clockSeqHi = hexdec(substr($hex, 16, 2)) & 0x3f;
        $clockSeqHi &= ~(0xc0);
        $clockSeqHi |= 0x80;

        $fields = array(
            'time_low' => substr($hex, 0, 8),
            'time_mid' => substr($hex, 8, 4),
            'time_hi_and_version' => sprintf('%04x', $timeHi),
            'clock_seq_hi_and_reserved' => sprintf('%02x', $clockSeqHi),
            'clock_seq_low' => substr($hex, 18, 2),
            'node' => substr($hex, 20, 12),
        );

        return vsprintf('%08s-%04s-%04s-%02s%02s-%012s', $fields);
    }
}
