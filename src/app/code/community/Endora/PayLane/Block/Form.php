<?php
/**
 * Payment form markup block.
 * 
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Block_Form extends Mage_Core_Block_Template {
    protected $helper;
    
    protected function _construct()  
    {  
        parent::_construct();  
        $this->setTemplate('paylane/form.phtml');
        $this->helper = Mage::helper('paylane');
    }  
    
    /**
     * Returns payment types with labels and payment type image
     * 
     * @return array(
     *  paymentTypeCode => array(
     *     label => "Label that will be shown on checkout page. Can be translated.",
     *     img => Url to image file, that will be displayed instead of label
     *  )
     * )
     */
    public function getPaymentMethods()
    {   
        $classNames = $this->helper->getPaymentTypeClasses();
        
        foreach ($classNames as $class) {
            $paymentClass = Mage::getModel('paylane/api_payment_'.$class);
            $result[$paymentClass->getCode()] = array(
                'label' => $paymentClass->getLabel(),
                'img' => $paymentClass->getImageUrl(),
                'recurring' => $paymentClass->isRecurringPayment()
            );
        }
        
        return $result;
    }
    
    /**
     * Check whether payment method is active
     * 
     * @param string $code 
     * @return boolean
     */
    public function isPaymentMethodActive($code)
    {
        return Mage::getStoreConfig($this->helper->getPaymentMethodStoreConfigStringPrefix($code) . '/active');
    }
    
    /**
     * Check whether recurring item is in a shopping cart
     * 
     * @return boolean
     */
    public function isRecurringItemInCart()
    {
        $result = false;
        $cartItems = Mage::getSingleton('checkout/cart')->getItems();
        
        foreach ($cartItems as $item) { 
            if($item->getProduct()->getIsRecurring()){
                $result = true;
                break;
            }
        }
        
        return $result;
    }
    
}