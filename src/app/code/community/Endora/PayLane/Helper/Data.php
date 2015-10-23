<?php
/**
 * Basic module helper
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Helper_Data extends Mage_Core_Helper_Data {
    const XML_CONFIG_SEND_CUSTOMER_DATA = 'payment/paylane_secureform/send_customer_data';
    const XML_CONFIG_MERCHANT_ID = 'payment/paylane_secureform/merchant_id';
    const XML_CONFIG_HASH_SALT = 'payment/paylane_general/hash_salt';
    const XML_CONFIG_GATEWAY_TYPE = 'payment/paylane/gateway_type';
    const XML_CONFIG_REDIRECT_VERSION = 'payment/paylane_general/redirect_version';
    const XML_CONFIG_PENDING_ORDER_STATUS = 'payment/paylane_general/pending_order_status';
    const XML_CONFIG_PERFORMED_ORDER_STATUS = 'payment/paylane_general/performed_order_status';
    const XML_CONFIG_CLEARED_ORDER_STATUS = 'payment/paylane_general/cleared_order_status';
    const XML_CONFIG_ERROR_ORDER_STATUS = 'payment/paylane_general/error_order_status';
    const XML_CONFIG_FRAUD_CHECK = 'payment/paylane_creditcard/fraud_check';
    const XML_CONFIG_OVERWRITE_FRAUD_CHECK = 'payment/paylane_creditcard/fraud_check_overwrite';
    const XML_CONFIG_CREDIT_CARD_AUTHORIZATION_AMOUNT = 'payment/paylane_creditcard/authorization_amount';
    const XML_CONFIG_CREDIT_CARD_SINGLE_CLICK_ACTIVE = 'payment/paylane_creditcard/single_click_active';
    const XML_CONFIG_AVS_CHECK_LEVEL = 'payment/paylane_creditcard/avs_check_level';
    const XML_CONFIG_OVERWRITE_AVS_CHECK_LEVEL = 'payment/paylane_creditcard/avs_check_level_overwrite';
    const XML_CONFIG_NOTIFICATIONS_USERNAME = 'payment/paylane_notifications/username';
    const XML_CONFIG_NOTIFICATIONS_PASSWORD = 'payment/paylane_notifications/password';
    
    const GATEWAY_TYPE_SECURE_FORM = 'secureForm';
    const GATEWAY_TYPE_API = 'API';
    
    const URL_SUCCESS_PATH = 'checkout/onepage/success';
    const URL_FAILURE_PATH = 'checkout/onepage/failure';
  
    public function isSingleClickActive()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_CREDIT_CARD_SINGLE_CLICK_ACTIVE);
    }
    
    public function canOverwriteFraudCheck()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_OVERWRITE_FRAUD_CHECK);
    }
    
    public function canOverwriteAvsCheckLevel()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_OVERWRITE_AVS_CHECK_LEVEL);
    }
    
    public function getAvsCheckLevel()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_AVS_CHECK_LEVEL); //temporarily disabled
    }
    
    public function isFraudCheck()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_FRAUD_CHECK);
    }
    
    public function getRedirectVersion()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_REDIRECT_VERSION);
    }
    
    public function getGatewayType()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_GATEWAY_TYPE);
    }
    
    public function isCustomerDataEnabled()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_SEND_CUSTOMER_DATA);
    }
    
    public function getMerchantId()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_MERCHANT_ID);
    }
    
    public function getHashSalt($paymentMethod = null)
    {
        if(is_null($paymentMethod)) {
            $hashSalt = Mage::getStoreConfig(self::XML_CONFIG_HASH_SALT);
        } else {
            $hashSalt = Mage::getStoreConfig($this->getPaymentMethodStoreConfigStringPrefix($paymentMethod).'/hash_salt');
        }
        
        return $hashSalt;
    }
    
    public function getPendingOrderStatus()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_PENDING_ORDER_STATUS);
    }
    
    public function getPerformedOrderStatus()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_PERFORMED_ORDER_STATUS);
    }
    
    public function getClearedOrderStatus()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_CLEARED_ORDER_STATUS);
    }
    
    public function getErrorOrderStatus()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_ERROR_ORDER_STATUS);
    }
    
    public function getBackUrl()
    {
        return Mage::getUrl('paylane/payment/secureFormResponse', array('_secure' => true));
    }
    
    public function getRedirectUrl($errors = false)
    {
        $redirect = self::URL_FAILURE_PATH;
                
        if(!$errors) {
            $redirect = self::URL_SUCCESS_PATH;
        }
        
        return $redirect;
    }
    
    public function getStateByStatus($status) {
        $model = Mage::getResourceModel('sales/order_status_collection')
            ->joinStates()
            ->addFieldToFilter('main_table.status', $status)
            ->getFirstItem();
        
        return $model['state'];
    }
    
    /**
     * Method that gets payment type classes. It search for payment types
     * implemented in Endora/PayLane/Model/Api/Payment and return
     * list of class names. It is useful for simple extending number
     * of implemented payment classes without changes in other files.
     * 
     * @return array Class names connected with payment types
     */
    public function getPaymentTypeClasses()
    {
        $paymentTypes = glob(__DIR__ . '/../Model/Api/Payment/*.php');
        $classNames = array();
        
        foreach($paymentTypes as $paymentType) {
            $paymentType = str_replace(__DIR__ . '/../Model/Api/Payment/', '', $paymentType);
            $paymentType = str_replace('.php', '', $paymentType);
            $classNames[] = $paymentType;
        }
        
        return $classNames;
    }
    
    public function getBankTransferPaymentTypes()
    {
        $result = array(
            'AB' => array(
                'label' => 'Alior Bank',
                'img' => null
            ),
            'AS' => array(
                'label' => 'T-Mobile Usługi Bankowe',
                'img' => null
            ),
            'MU' => array(
                'label' => 'Multibank',
                'img' => null
            ),
            'MT' => array(
                'label' => 'mTransfer',
                'img' => null
            ),
            'IN' => array(
                'label' => 'Inteligo',
                'img' => null
            ),
            'IP' => array(
                'label' => 'iPKO',
                'img' => null
            ),
            'DB' => array(
                'label' => 'Deutsche Bank',
                'img' => null
            ),
            'MI' => array(
                'label' => 'Millenium',
                'img' => null
            ),
            'CA' => array(
                'label' => 'Credit Agricole',
                'img' => null
            ),
            'PP' => array(
                'label' => 'Poczta Polska',
                'img' => null
            ),
            'BP' => array(
                'label' => 'Bank BPH',
                'img' => null
            ),
            'IB' => array(
                'label' => 'Idea Bank',
                'img' => null
            ),
            'PO' => array(
                'label' => 'Pekao S.A.',
                'img' => null
            ),
            'GB' => array(
                'label' => 'Getin Bank',
                'img' => null
            ),
            'IG' => array(
                'label' => 'ING Bank Śląski',
                'img' => null
            ),
            'WB' => array(
                'label' => 'Bank Zachodni WBK',
                'img' => null
            ),
            'OH' => array(
                'label' => 'Other',
                'img' => null
            ),
        );
        
        return $result;
    }
    
    /**
     * Returns XML Config Path for appropriate Payment Method
     * 
     * @param string $methodCode
     * @return string
     */
    public function getPaymentMethodStoreConfigStringPrefix($methodCode)
    {
        return 'payment/paylane_' . strtolower($methodCode);
    }
    
    public function getNotificationsUsername()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_NOTIFICATIONS_USERNAME);
    }
    
    public function getNotificationsPassword()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_NOTIFICATIONS_PASSWORD);
    }
    
    public function getCreditCardAuthorizationAmount()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_CREDIT_CARD_AUTHORIZATION_AMOUNT);
    }
    
    public function getOrderByPaylaneSaleId($saleId)
    {
        return Mage::getModel('sales/order')->load($saleId, 'paylane_sale_id');
    }
    
    public function generateMonthsNumber()
    {
        $result = array();
        
        for($i=1;$i<=12;$i++) {
            $result[$i] = sprintf("%02d", $i);
        }
        
        return $result;
    }
    
    /**
     * Generate array with years list
     * 
     * @return array List of years from current year to 100 years later
     */
    public function generateCreditCardValidYears()
    {
        $result = array();
        $currYear = date("Y");
        for($i = $currYear; $i < $currYear + 100; $i++) {
            $result[$i] = $i;
        }
        
        return $result;
    }
}
