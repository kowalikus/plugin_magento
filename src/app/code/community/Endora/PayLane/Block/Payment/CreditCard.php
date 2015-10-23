<?php
/**
 * Block to handle credit card payment type
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Block_Payment_CreditCard extends Mage_Core_Block_Template {
    private $customer = null;
    
    public function isCustomerAuthorized()
    {
        $customer = $this->_getCustomer();
        if($customer->getCardAuthorizationId()) {
            return true;
        } else {
            return false;
        }
    }
    
    public function isCustomerFirstOrder()
    {
        $customer = $this->_getCustomer();
        $orderAmount = Mage::getResourceModel('sales/order_collection')
            ->addFieldToSelect('entity_id')
            ->addFieldToFilter('customer_id', $customer->getId())
            ->count();
        
        if ($orderAmount - 1 <= 0) { // without currently processed
            return true;
        } else {
            return false;
        }
    }
    
    public function getCustomerLastOrderPaylaneSaleId()
    {
        $customer = $this->_getCustomer();
        $order = Mage::getResourceModel('sales/order_collection')
            ->addFieldToSelect('entity_id')
            ->addAttributeToSelect('paylane_sale_id')
            ->addFieldToFilter('customer_id', $customer->getId())
            ->addFieldToFilter('paylane_sale_id', array('notnull' => true))
            ->addAttributeToSort('created_at', 'DESC')
            ->setCurPage(1)
            ->setPageSize(1)
            ->getFirstItem();
        
        return $order->getPaylaneSaleId();
    }
    
    protected function _getCustomer()
    {
        if(is_null($this->customer)) {
            $customerId = Mage::getSingleton('customer/session')->getId();
            $this->customer = Mage::getModel('customer/customer')->load($customerId);
        }
        
        return $this->customer;
    }
    
}
