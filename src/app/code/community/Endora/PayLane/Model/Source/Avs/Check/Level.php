<?php
/**
 * Source model for allowed AVS check levels
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Source_Avs_Check_Level {
    
    public function toOptionArray()
    {
        return array(
            array('value' => 0, 'label' => '0'),
            array('value' => 1, 'label' => '1'),
            array('value' => 2, 'label' => '2'),
            array('value' => 3, 'label' => '3'),
            array('value' => 4, 'label' => '4'),
        );
    }
    
}
