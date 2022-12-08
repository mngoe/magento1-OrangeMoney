<?php
 
class Ynote_Orangemoney_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
 
	protected $_code = 'orangemoney';
	protected $_infoBlockType = 'orangemoney/info';
	 
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = false;
	protected $_canUseForMultishipping  = false;
 
	/**
	* Return Order place redirect url
	*
	* @return string
	*/
	public function getOrderPlaceRedirectUrl(){
		return Mage::getUrl('orangemoney/index/redirect', array('_secure' => true));
	}


    

 
}