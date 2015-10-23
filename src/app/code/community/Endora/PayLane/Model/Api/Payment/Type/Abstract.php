<?php
/**
 * Abstract class that handles basic function of API payment model
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
abstract class Endora_PayLane_Model_Api_Payment_Type_Abstract extends Mage_Core_Model_Abstract {
    protected $_paymentTypeCode = 'paymentType';
    protected $_isRecurringPayment = false;
    protected $_paymentImgUrl = null;
    protected $_client;
    
    public function __construct() {
        $this->_client = Mage::getSingleton('paylane/api_client');
        $this->_client->authorize($this->_paymentTypeCode);
    }
    
    abstract public function handlePayment(Mage_Sales_Model_Order $order, $additionalParameters = null);
    
    public function getCode()
    {
        return $this->_paymentTypeCode;
    }
    
    public function getStoreConfigStringPrefix()
    {
        return Mage::helper('paylane')->getPaymentMethodStoreConfigStringPrefix($this->_paymentTypeCode);
    }
    
    public function getLabel()
    {
        return Mage::getStoreConfig($this->getStoreConfigStringPrefix() . '/title');
    }
    
    public function getImageUrl()
    {
        return $this->_paymentImgUrl;
    }
    
    public function getClient()
    {
        return $this->_client;
    }
    
    public function isRecurringPayment()
    {
        return $this->_isRecurringPayment;
    }
    
    /**
     * Prepares order data for API request
     * 
     * @param Mage_Sales_Model_Order $order
     * @return array Array of order data
     */
    protected function _prepareSaleData($order)
    {
        $result = array();   
        $result['amount'] = sprintf('%01.2f', $order->getGrandTotal());
        $result['currency'] = $order->getOrderCurrencyCode();
        $result['description'] = $order->getIncrementId();
        
        return $result;
    }
    
    /**
     * Prepares customer data from order for API request
     * 
     * @param Mage_Sales_Model_Order $order
     * @return array Array of order data
     */
    protected function _prepareCustomerData($order)
    {
        $result = array();   
        
        $address = $order->getBillingAddress();
        $result['name'] = $order->getCustomerName();
        $result['email'] = $address->getEmail();
        $result['ip'] = $order->getRemoteIp();
        $result['address'] = array(
            'city' => $address->getCity(),
            'state' => $address->getRegion(),
            'country_code' => $address->getCountry(),
            'zip' => $address->getPostcode(),
            'street_house' => $address->getStreet(true)
        );
        
        return $result;
    }
    
    /**
     * Prepares sale data for recurring payment
     * 
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return type
     */
    protected function _prepareRecurringSaleData(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $result = array();   
        $result['amount'] = sprintf('%01.2f', $profile->getBillingAmount());
        $result['currency'] = $profile->getCurrencyCode();
        $result['description'] = $profile->getScheduleDescription();
        
        return $result;
    }
    
    /**
     * Prepares customer data from recurring profile for API request
     * Used in recurring payments
     * 
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return array Array of order data
     */
    protected function _prepareRecurringCustomerData(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $result = array();
        $order = $profile->getOrderInfo();
        $address = $profile->getBillingAddressInfo();
        
        $result['name'] = $order['customer_firstname'] . ' ' . $order['customer_lastname'];
        $result['email'] = $order['customer_email'];
        $result['ip'] = $order['remote_ip'];
        $result['address'] = array(
            'city' => $address['city'],
            'state' => $address['region'],
            'country_code' => $address['country_id'],
            'zip' => $address['postcode'],
            'street_house' => $address['street']
        );
        
        return $result;
    }
}
