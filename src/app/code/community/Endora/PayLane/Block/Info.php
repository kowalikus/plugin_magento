<?php

class Endora_PayLane_Block_Info extends Mage_Payment_Block_Info {

    protected $result;
    protected $order;
    protected $payment;
    
    public function _construct() {
        parent::_construct();
        $this->setTemplate('paylane/info.phtml');
    }
    
    protected function getOrder() {
        if (!$this->order) {
            $this->order = $this->getInfo()->getOrder();
        }
        return $this->order;
    }
    
    protected function getQuote() {
        if (!$this->quote) {
            $this->quote = $this->getInfo()->getQuote();
        }
        return $this->quote;
    }
    
    public function getMoreInfo() {
        $order = $this->getOrder();
        
        if ($order) {
            $payment = $order->getPayment();
        } else {
            $quote = $this->getQuote();
            $payment = $quote->getPayment();
        }
        
        $paymentType = $payment->getAdditionalInformation('paylane_payment_type');
        $paymentModel = Mage::getModel('paylane/api_payment_'.$paymentType);

        $result = array(
            'Payment channel' => $paymentModel->getLabel()
        );

        if($paymentModel instanceof Endora_PayLane_Model_Interface_PaymentTypeExtendedInfo) {
            $additionalInfo = $paymentModel->getAdditionalInfo($payment);
            $result = array_merge($result, $additionalInfo);
        }
        
        return $result;
    }
	
}