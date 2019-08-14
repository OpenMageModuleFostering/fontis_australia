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
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Fontis
 * @package    Fontis_Australia
 * @author     Chris Norton
 * @copyright  Copyright (c) 2008 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Australia Post shipping model
 *
 * @category   Fontis
 * @package    Fontis_Australia
 */
class Fontis_Australia_Model_Shipping_Carrier_Australiapost
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{

    protected $_code = 'australiapost';

    /**
     * Collects the shipping rates for Australia Post from the DRC API.
     *
     * @param Mage_Shipping_Model_Rate_Request $data
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
    	// Check if this method is active
		if (!$this->getConfigFlag('active')) 
		{
			return false;
		}
		
		// Check if this method is even applicable (shipping from Australia)
		$origCountry = Mage::getStoreConfig('shipping/origin/country_id', $this->getStore());
		if ($origCountry != "AU") 
		{
			return false;
		}

		$result = Mage::getModel('shipping/rate_result');

		// TODO: Add some more validations
		$frompcode = Mage::getStoreConfig('shipping/origin/postcode', $this->getStore());
		$topcode = $request->getDestPostcode();

		if ($request->getDestCountryId()) 
		{
			$destCountry = $request->getDestCountryId();
		} 
		else 
		{
			$destCountry = "AU";
		}

		// Here we get the weight (and convert it to grams) and set some
		// sensible defaults for other shipping parameters.	
		$sweight = (float)$request->getPackageWeight() * $this->getConfigData('weight_units');
		$sheight = $swidth = $slength = 100;
		$shipping_num_boxes = 1;

		// Switch between domestic and international shipping methods based
		// on destination country.
		if($destCountry == "AU")
		{
			$shipping_methods = array("STANDARD", "EXPRESS");
		}
		else
		{
			$shipping_methods = array("SEA", "AIR");
		}

        foreach($shipping_methods as $shipping_method)
        {
        	// Construct the appropriate URL and send all the information
        	// to the Australia Post DRC.
	        $url = "http://drc.edeliver.com.au/ratecalc.asp?" . 
	        	"Pickup_Postcode=" . $frompcode .
	        	"&Destination_Postcode=" . $topcode .
	        	"&Country=" . $destCountry .
	        	"&Weight=" . $sweight .
	        	"&Service_Type=" . $shipping_method . 
	        	"&Height=" . $sheight . 
	        	"&Width=" . $swidth . 
	        	"&Length=" . $slength .
	        	"&Quantity=" . $shipping_num_boxes;
	        	
			$drc_result = file($url);
			foreach($drc_result as $vals)
			{
					$tokens = split("=", $vals);
					$$tokens[0] = $tokens[1];
			}
			
			// Check that the DRC returned without error.
			if(trim($err_msg) == "OK")
			{
				$shippingPrice = $request->getBaseCurrency()->convert($charge, $request->getPackageCurrency());

				$shippingPrice += $this->getConfigData('handling_fee');

				$method = Mage::getModel('shipping/rate_result_method');

				$method->setCarrier('australiapost');
				$method->setCarrierTitle($this->getConfigData('title'));

				$method->setMethod($shipping_method);
				$title_days = ($days == 1) ? " (1 day)" : " ($days days)";
				$title = $this->getConfigData('name') . " " . 
						ucfirst(strtolower($shipping_method)) . 
						$title_days;
				$method->setMethodTitle($title);

				$method->setPrice($shippingPrice);
				$method->setCost($shippingPrice);

				$result->append($method);
			}
		}
		
        return $result;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array('australiapost' => $this->getConfigData('name'));
    }

}
