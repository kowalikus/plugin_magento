<?php
/**
 * Payment model for PayPal payment channel
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Api_Payment_PayPal extends Endora_PayLane_Model_Api_Payment_Type_Abstract {
    const RETURN_URL_PATH = 'paylane/payment/externalUrlResponse';
    
    protected $_paymentTypeCode = 'payPal';
    protected $_isRecurringPayment = true;

    public function handlePayment(Mage_Sales_Model_Order $order, $params = null) {
        $data = array();
        $client = $this->getClient();
        $helper = Mage::helper('paylane');
        
        $data['sale'] = $this->_prepareSaleData($order);
        if(!empty($params['redirect_url'])) {
            $data['back_url'] = $params['redirect_url'];
        } else {
            $data['back_url'] = Mage::getUrl(self::RETURN_URL_PATH, array('_secure' => true));
        }
        
        $result = $client->paypalSale($data);
        
        //probably should be in externalUrlResponseAction
        if($result['success']) {
            header('Location: ' . $result['redirect_url']);
            die;
        } else {
            $orderStatus = $helper->getErrorOrderStatus();
            $errorCode = '';
            $errorText = '';
            if(!empty($result['error'])) {
                $errorCode = (!empty($result['error']['error_number'])) ? $result['error']['error_number'] : '';
                $errorText = (!empty($result['error']['error_description'])) ? $result['error']['error_description'] : '';
            }
            $comment = $helper->__('There was an error in payment process via PayLane module (Error code: %s, Error text: %s)', $errorCode, $errorText);
            $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
            $order->save();
        }
        
        return $result['success'];
    }
    
    /**
     * Method to handle recurring payment process
     * 
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param array $params
     * @return boolean Success flag
     */
    public function handleRecurringPayment(Mage_Payment_Model_Recurring_Profile $profile, $params = null)
    {
        $data = array();
        $client = $this->getClient();
        
        $data['sale'] = $this->_prepareRecurringSaleData($profile);
        $data['customer'] = $this->_prepareRecurringCustomerData($profile);
        $data['back_url'] = Mage::getUrl(self::RETURN_URL_PATH, array('_secure' => true));
        $data['account'] = $params;
        
        $result = $client->paypalSale($data);
        
        return $result;
    }
}
