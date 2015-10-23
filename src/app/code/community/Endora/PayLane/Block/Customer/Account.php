<?php

class Endora_PayLane_Block_Customer_Account extends Mage_Core_Block_Template {
    
    public function isCustomerAuthorized()
    {
        $customerId = Mage::getSingleton('customer/session')->getId();
        
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if($customer->getCardAuthorizationId()) {
            return true;
        } else {
            return false;
        }
    }
    
}