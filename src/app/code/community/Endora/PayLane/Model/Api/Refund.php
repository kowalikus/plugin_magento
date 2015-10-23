<?php
/**
 * API Resales handler
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */

class Endora_PayLane_Model_Api_Refund extends Mage_Core_Model_Abstract {
    protected $_client;

    public function __construct() 
    {
        $this->_client = Mage::getModel('paylane/api_client');
    }
    
    public function refund(Mage_Sales_Model_Order $order, $amount, $reason = null)
    {
        $saleId = $order->getPaylaneSaleId();
        $paymentCode = strtolower($order->getPayment()->getAdditionalInformation('paylane_payment_type'));
                
        if(is_null($saleId)) {
            return false;
        }
        
        if(is_null($reason)) {
            $reason = 'Refund for order #' . $order->getIncrementId();
        }
        
        $refundParams = array(
            'id_sale'  => $saleId,
            'amount'   => $amount,
            'reason'   => $reason,
        );
        
        $this->_client->authorize($paymentCode);
        $result = $this->_client->refund($refundParams);
        
        return $result;
    }
}
