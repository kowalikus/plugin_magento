<?php
/**
 * Payment model for iDEAL Banking payment channel
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Api_Payment_Ideal extends Endora_PayLane_Model_Api_Payment_Type_Abstract
    implements Endora_PayLane_Model_Interface_PaymentTypeExtendedInfo {
    const RETURN_URL_PATH = 'paylane/payment/externalUrlResponse';
    
    protected $_paymentTypeCode = 'ideal';

    public function handlePayment(Mage_Sales_Model_Order $order, $params = null) {
        $data = array();
        $client = $this->getClient();
        $helper = Mage::helper('paylane');
        
        $data['sale'] = $this->_prepareSaleData($order);
        $data['customer'] = $this->_prepareCustomerData($order);
        $data['back_url'] = Mage::getUrl(self::RETURN_URL_PATH, array('_secure' => true));
        $data['bank_code'] = $params['bank_code'];
        
        $payment = $order->getPayment();
        $additional = $payment->getAdditionalInformation();
        $additional['paylane_payment_bank'] = $params['bank_code'];
        $payment->setAdditionalInformation($additional);
        $payment->save();
        
        $result = $client->idealSale($data);
        
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
     * Fetch list of iDEAL bank codes
     * 
     * @return boolean
     */
    public function getBankCodes()
    {
        try {
            $client = $this->getClient();
            $result = $client->idealBankCodes();
        } catch (Exception $e) {
            return false;
        }
        
        /**
         * @todo Better error handling
         */
        if (!$client->isSuccess()) {
            $error_number = $result['error']['error_number'];
            $error_description = $result['error']['error_description'];
            return false;
        }
        
        return $result['data'];
    }
    
    /**
     * Method that allows to use additional info in order summary in admin panel
     * 
     * @see Endora_PayLane_Block_Info
     * @see design/adminhtml/base/default/template/paylane/info.phtml
     */
    public function getAdditionalInfo($payment = null)
    {
        $result = array();
        $paymentBank = $payment->getAdditionalInformation('paylane_payment_bank');
        $bankCodes = $this->getBankCodes();
        $label = 'Unknown';
        
        if($paymentBank) {
            foreach($bankCodes as $bank) {
                if($bank['bank_code'] == $paymentBank) {
                    $label = $bank['bank_name'];
                    break;
                }
            }

            $result['Chosen bank'] = $label;
        }
        
        return $result;
    }
}
