<?php
/**
 * Block to handle iDEAL payment type
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Block_Payment_Ideal extends Mage_Core_Block_Template {
    
    public function getBankCodes()
    {
        return Mage::getModel('paylane/api_payment_ideal')->getBankCodes();
    }
    
}
