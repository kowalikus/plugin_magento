<?php
/**
 * Block to handle bank transfer payment type
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Block_Payment_BankTransfer extends Mage_Core_Block_Template {
    
    /**
     * Returns list of payment types
     * 
     * @link http://devzone.paylane.pl/opis-funkcji/#banktransfers-sale
     * @return array
     */
    public function getPaymentTypes()
    {   
        $result = Mage::helper('paylane')->getBankTransferPaymentTypes();
        return $result;
    }
    
}
