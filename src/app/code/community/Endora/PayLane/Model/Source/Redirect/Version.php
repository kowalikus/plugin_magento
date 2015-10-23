<?php
/**
 * Source model for allowed redirect version
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Source_Redirect_Version {
    
    public function toOptionArray()
    {
        $helper = Mage::helper('paylane');
        return array(
            array('value' => 'GET', 'label' => $helper->__('GET')),
            array('value' => 'POST', 'label' => $helper->__('POST')),
        );
    }
    
}
