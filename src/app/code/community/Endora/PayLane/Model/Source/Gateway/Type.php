<?php
/**
 * Source model for allowed gateway types
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Source_Gateway_Type {
    
    public function toOptionArray()
    {
        $helper = Mage::helper('paylane');
        return array(
            array('value' => 'SecureForm', 'label' => $helper->__('SecureForm')),
            array('value' => 'API', 'label' => $helper->__('API')),
        );
    }
    
}
