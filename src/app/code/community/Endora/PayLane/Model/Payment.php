<?php
/**
 * Payment model for PayLane payment method
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Payment extends Mage_Payment_Model_Method_Abstract
    implements Mage_Payment_Model_Recurring_Profile_MethodInterface {
    const TRANSACTION_TYPE_SALE = 'S';
    const TRANSACTON_TYPE_AUTHORIZATION = 'A';
    const SECURE_FORM_DEFAULT_LANGUAGE = 'en';
    const SECURE_FORM_GATEWAY_URL = 'https://secure.paylane.com/order/cart.html';
    
    /**
     * @see http://devzone.paylane.pl/secure-form/realizacja/ 
     */
    const PAYMENT_STATUS_PENDING = 'PENDING';
    const PAYMENT_STATUS_PERFORMED = 'PERFORMED';
    const PAYMENT_STATUS_CLEARED = 'CLEARED';
    const PAYMENT_STATUS_ERROR = 'ERROR';
    
    protected $secureFormAllowedLanguages = array('pl', 'en', 'de', 'es', 'fr', 'nl');
    
    protected $_code = 'paylane';
    protected $_formBlockType = 'paylane/form';
    protected $_infoBlockType = 'paylane/info';
 
    /**
     * Updated payment Method features
     * 
     * @var bool
     * @see Mage_Payment_Model_Method_Abstract
     */
    protected $_isGateway                   = false;
    protected $_canOrder                    = false;
    protected $_canAuthorize                = false;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = false;
    protected $_canCaptureOnce              = false;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = false;
    protected $_canUseInternal              = true;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = false;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canReviewPayment            = true;
    protected $_canCreateBillingAgreement   = false;
    protected $_canManageRecurringProfiles  = true;
    
   /**
    * Return Order place redirect url
    *
    * @return string
    */
    public function getOrderPlaceRedirectUrl()
    {
        //when you click on place order you will be redirected on this url, if you don't want this action remove this method
        return Mage::getUrl('paylane/payment/redirect', array('_secure' => true));
    }
    
    public function getSecureFormLanguage($langCode)
    {
        return in_array($langCode, $this->secureFormAllowedLanguages) ? $langCode : self::SECURE_FORM_DEFAULT_LANGUAGE;
    }
    
    public function getGatewayUrl()
    {
        return self::SECURE_FORM_GATEWAY_URL;
    }
    
    /**
     * Calculating verification hash - used only in SecureForm
     * 
     * @param type $description
     * @param type $amount
     * @param type $currency
     * @param type $transactionType
     * @return type
     */
    public function calculateHash($description, $amount, $currency, $transactionType)
    {
        $secureForm = Mage::getModel('paylane/api_payment_secureForm');
        $salt = Mage::helper('paylane')->getHashSalt($secureForm->getCode());
        $hash = sha1($salt . '|' . $description . '|' . $amount . '|' . $currency . '|' . $transactionType);
        
        return $hash;
    }
    
    public function getTransactionId($params)
    {
        $id = null;
        
        if(!empty($params['id_sale'])) {
            $id = $params['id_sale'];
        } else if (!empty($params['id_authorization'])) {
            $id = $params['id_authorization'];
        }
        
        return $id;
    }
    
    public function verifyResponseHash($params, $paymentType = null)
    {   
        $id = $this->getTransactionId($params);
        $salt = Mage::helper('paylane')->getHashSalt($paymentType);
        
        $calculatedHash = sha1($salt . '|' . $params['status'] . '|' . $params['description'] . '|' . $params['amount'] . '|' . $params['currency'] . '|' . $id);
        
        return ($calculatedHash == $params['hash']);
    }
    
    public function preparePostData($order)
    {
        $helper = Mage::helper('paylane');
        /**
         * collect data for PayLane request
         * 
         * @see http://devzone.paylane.pl/secure-form/realizacja/
         */
        $postData = array();
        $postData['merchant_id'] = $helper->getMerchantId();
        $postData['description'] = $order->getIncrementId();
        $postData['transaction_description'] = $this->_buildTransactionDescription($order);
        $postData['amount'] = sprintf('%01.2f', $order->getGrandTotal());
        $postData['currency'] = $order->getOrderCurrencyCode();
        $postData['transaction_type'] = Endora_PayLane_Model_Payment::TRANSACTION_TYPE_SALE;
        $postData['back_url'] = $helper->getBackUrl();
        $postData['hash'] = $this->calculateHash($postData['description'], $postData['amount'], $postData['currency'], $postData['transaction_type']);
        $postData['language'] = $this->getSecureFormLanguage(substr(Mage::app()->getLocale()->getLocaleCode(), 0 , 2));
        
        if ($helper->isCustomerDataEnabled()) {
           $address = $order->getBillingAddress();
           $postData['customer_name'] = $order->getCustomerName();
           $postData['customer_email'] = $address->getEmail();
           $postData['customer_address'] = $address->getStreet(true);
           $postData['customer_zip'] = $address->getPostcode();
           $postData['customer_city'] = $address->getCity();
           $postData['customer_state'] = $address->getRegion();
           $postData['customer_country'] = $address->getCountry();
        }
        
        return $postData;
    }
    
    protected function _buildTransactionDescription($order)
    {
        $resultString = Mage::helper('paylane')->__('Order #%s, %s (%s)', $order->getIncrementId(), $order->getCustomerName(), $order->getCustomerEmail());
        
        return $resultString;
    }
    
    public function capture(Varien_Object $payment, $amount) {
        $paylaneId = $payment->getOrder()->getPaylaneSaleId();
        
        if(!$paylaneId) { // in case when paylane sale id is not yet set
            $paylaneId = rand();
        }
        
        $payment->setTransactionId($paylaneId);
        $payment->setParentTransactionId($payment->getTransactionId());
        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, true, "");
        $transaction->setIsClosed(true);
        $payment->setIsTransactionClosed(1);
        
        return $this;
    }
    
    public function processBeforeRefund($invoice, $payment)
    {
        return parent::processBeforeRefund($invoice, $payment);
    }
    
    public function refund(Varien_Object $payment, $amount)
    {
        $helper = Mage::helper('paylane');
        $refundModel = Mage::getModel('paylane/api_refund');
        $order = $payment->getOrder();

        $result = $refundModel->refund($order, $amount);
        
        if($result['success']) {
            $order->addStatusHistoryComment($helper->__('Refund was handled via PayLane module | Refund ID: %s', $result['id_refund']));
            $order->save();
        } else {
            $errorMsg = $helper->__('Error Processing the request | PayLane module refund process failed');
            Mage::throwException($errorMsg);
        }
            
        return $this;
    }
    
    public function processCreditmemo($creditmemo, $payment)
    {
        return parent::processCreditmemo($creditmemo, $payment);
    }
    
    /**
     * Submit to the gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $payment
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, Mage_Payment_Model_Info $payment)
    {
        $paymentType = $payment->getAdditionalInformation('paylane_payment_type');
        $paymentParams = Mage::getSingleton('checkout/session')->getData('payment_params');
        Mage::getSingleton('checkout/session')->unsetData('payment_params');
        $paymentParams['redirect_url'] = Mage::getUrl('paylane/payment/recurringProfileResponse', array('_secure' => true));
        $apiPayment = Mage::getModel('paylane/api_payment_' . $paymentType);
        $result = $apiPayment->handleRecurringPayment($profile, $paymentParams);
        
        if($result['success']) {
            $profile->setReferenceId( $result['id_sale'] );
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
            $payment->setSkipTransactionCreation(true);
        
            if ((float)$profile->getInitAmount()){
                $orderItem = new Varien_Object;
                $orderItem->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_INITIAL);
                $orderItem->setPrice($profile->getInitAmount());

                $order = $profile->createOrder($orderItem);
                $transactionId = 'paylane-trans-' . uniqid();
                $payment = $order->getPayment();
                $payment->setTransactionId($transactionId)->setIsTransactionClosed(1);
                $order->save();
                
                $profile->addOrderRelation($order->getId());
                $order->save();
                $payment->save();

                $this->_createOrderTransaction($transactionId, $order, $payment);
            }
        }
        
        return $this;
    }
    
    /**
     * Handle charging for recurring profile
     * 
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return boolean
     */
    public function chargeRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile){
        
        $client = Mage::getModel('paylane/api_client');
        $client->authorize('notifications');
        $params = array(
            'id_sale' => $profile->getReferenceId(),
            'amount' => $profile->getBillingAmount(),
            'currency' => $profile->getCurrencyCode(),
            'description' => 'Recuring payment for Sale ID ' . $profile->getReferenceId()
        );
        $result = $client->resaleBySale($params);

        if ($result['success']){
            $orderItem = new Varien_Object;
            $orderItem->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR);
            $orderItem->setPrice( $profile->getTaxAmount() + $profile->getBillingAmount() + $profile->getShippingAmount() );

            $order = $profile->createOrder($orderItem);
            $order->setState(Mage_Sales_Model_Order::STATE_NEW);
            $transactionId = 'paylane-trans-' . uniqid();
            $payment = $order->getPayment();
            $payment->setTransactionId($transactionId)->setIsTransactionClosed(1);
            $order->save();
            
            $profile->addOrderRelation($order->getId());
            $payment->save();
            
            $this->_createOrderTransaction($transactionId, $order, $payment);
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
            $this->_setNextPeriodDate($profile->getId());

            return true;

        }

        return false;
    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this;
    }
    
    /**
     * Validate data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @throws Mage_Core_Exception
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this;
    }
    
    /**
     * Fetch details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
    {
        return $this;
    }

    /**
     * Check whether can get recurring profile details
     *
     * @return bool
     */
    public function canGetRecurringProfileDetails()
    {
        return true;
    }

    /**
     * Update data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this;
    }
    
    protected function _setNextPeriodDate($profileId){
        $resourceModel = Mage::getSingleton('core/resource');
        $tableName = $resourceModel->getTableName('sales_recurring_profile');
        
        $sql = 'UPDATE '.$tableName.'
                SET updated_at = CASE period_unit
                        WHEN "day" 			THEN DATE_ADD(updated_at, INTERVAL period_frequency DAY)
                        WHEN "week" 		THEN DATE_ADD(updated_at, INTERVAL (period_frequency*7) DAY)
                        WHEN "semi_month" 	THEN DATE_ADD(updated_at, INTERVAL (period_frequency*14) DAY)
                        WHEN "month" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency MONTH)
                        WHEN "year" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency YEAR)
                END
                WHERE profile_id = :profileId';

        $connection = $resourceModel->getConnection('core_write');
        $statement = $connection->prepare($sql);
        $statement->bindValue(':profileId', $profileId);
        return $statement->execute();
    }
    
    /**
     * Create transaction for order from recurring profile
     * 
     * @param string $transactionId
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Payment_Model_Info $payment
     * @return string|NULL Returns created transaction ID or NULL if transaction doesn't exist
     */
    protected function _createOrderTransaction($transactionId, Mage_Sales_Model_Order $order, Mage_Payment_Model_Info $payment)
    {
        $txn= Mage::getModel('sales/order_payment_transaction');
        $txn->setTxnId($transactionId);
        $txn->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
        $txn->setPaymentId($payment->getId());
        $txn->setOrderId($order->getId());
        $txn->setOrderPaymentObject($payment);
        $txn->setIsClosed(1);
        $txn->save();
        
        return $txn->getTxnId();
    }
}
