<?php
/**
 * API Client Adapter
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 * @see {MAGENTO_DIR}/lib/paylane/client/paylane/PayLaneRestClient.php
 */
require_once Mage::getBaseDir('lib') . '/paylane/client/paylane/PayLaneRestClient.php';

class Endora_PayLane_Model_Api_Client extends PayLaneRestClient {
    private $helper;

    public function __construct() 
    {
        /**
         * authorization is defined in separated method "authorize()"
         */
        parent::__construct(null, null); 
        $this->helper = Mage::helper('paylane');
    }
    
    /**
     * Allows to authorize in PayLane REST Client
     * 
     * @param string $methodCodeOrUsername - In one argument variant method it is payment method code, in two argument variant method it is username
     * @param string $pass - Password to PayLane Merchant Account
     */
    public function authorize($methodCodeOrUsername = null, $pass = null)
    {
        if(is_null($pass)) {
//            if(is_null($methodCodeOrUsername)) {
//                $this->username = $this->helper->getDefaultUsername();
//                $this->password = $this->helper->getDefaultPassword();
//            } else {
                $this->username = Mage::getStoreConfig($this->helper->getPaymentMethodStoreConfigStringPrefix($methodCodeOrUsername).'/username');
                $this->password = Mage::getStoreConfig($this->helper->getPaymentMethodStoreConfigStringPrefix($methodCodeOrUsername).'/password');
//            }
        } else {
            $this->username = $methodCodeOrUsername;
            $this->password = $pass;
        }
    }
}
