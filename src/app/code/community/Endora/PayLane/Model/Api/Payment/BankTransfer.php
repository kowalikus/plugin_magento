<?php
/**
 * Payment model for Bank transfer payment channel
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Api_Payment_BankTransfer extends Endora_PayLane_Model_Api_Payment_Type_Abstract 
    implements Endora_PayLane_Model_Interface_PaymentTypeExtendedInfo {
    const RETURN_URL_PATH = 'paylane/payment/externalUrlResponse';
    
    protected $_paymentTypeCode = 'bankTransfer';

    public function handlePayment(Mage_Sales_Model_Order $order, $params = null) {
        $data = array();
        $client = $this->getClient();
        $helper = Mage::helper('paylane');
        
        $data['sale'] = $this->_prepareSaleData($order);
        $data['customer'] = $this->_prepareCustomerData($order);
        $data['payment_type'] = $params['payment_type'];
        $data['back_url'] = Mage::getUrl(self::RETURN_URL_PATH, array('_secure' => true));
        
        $payment = $order->getPayment();
        $additional = $payment->getAdditionalInformation();
        $additional['paylane_payment_bank'] = $params['payment_type'];
        $payment->setAdditionalInformation($additional);
        $payment->save();
        
        $result = $client->bankTransferSale($data);
        
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
     * Method that allows to use additional info in order summary in admin panel
     * 
     * @see Endora_PayLane_Block_Info
     * @see design/adminhtml/base/default/template/paylane/info.phtml
     */
    public function getAdditionalInfo($payment = null)
    {
        $result = array();
        $paymentBank = $payment->getAdditionalInformation('paylane_payment_bank');
        
        if($paymentBank) {
            $paymentBanks = Mage::helper('paylane')->getBankTransferPaymentTypes();
            $result['Chosen bank'] = $paymentBanks[$paymentBank]['label'];
        }
        
        return $result;
    }
}
