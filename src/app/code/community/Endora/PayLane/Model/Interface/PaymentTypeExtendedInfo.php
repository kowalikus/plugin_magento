<?php
/**
 * Interface to handle additional payment type extended info
 * 
 * If you want to show additional data in order summary connected with your payment type
 * then you have to implement that interface
 * 
 * For example @see Endora_PayLane_Model_Api_Payment_Ideal
 * 
 * @see Endora_PayLane_Block_Info
 * @see design/adminhtml/base/default/template/paylane/info.phtml
 * 
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
interface Endora_PayLane_Model_Interface_PaymentTypeExtendedInfo {
    
    /**
     * Allows to prepare additional info about payment $payment
     * 
     * @param type Mage_Payment_Model_Method_Abstract $payment
     */
    public function getAdditionalInfo($payment = null);
    
}
