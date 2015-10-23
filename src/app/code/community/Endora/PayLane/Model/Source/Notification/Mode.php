<?php
/**
 * Option array for 
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Source_Notification_Mode {
    
    public function toOptionArray()
    {
        $helper = Mage::helper('paylane');
        return array(
            array('value' => Endora_PayLane_Helper_Notification::NOTIFICATION_MODE_MANUAL, 'label' => $helper->__('Manual')),
            array('value' => Endora_PayLane_Helper_Notification::NOTIFICATION_MODE_AUTO, 'label' => $helper->__('Automatic')),
        );
    }
    
}
