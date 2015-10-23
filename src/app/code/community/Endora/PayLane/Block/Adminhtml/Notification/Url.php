<?php
/**
 * Add read-only input field with Automatic POST handler URL
 * 
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 * @link http://devzone.paylane.pl/powiadomienia-o-transakcjach/ Description of automatic transaction
 */
class Endora_PayLane_Block_Adminhtml_Notification_Url extends Mage_Adminhtml_Block_System_Config_Form_Field {
   /**
    * Returns html part of the setting
    *
    * @param Varien_Data_Form_Element_Abstract $element
    * @return string
    */
   protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
   {
       $this->setElement($element);
       $html = '<input type="text" value ="'. Mage::getUrl('paylane/notification/handleAuto') .'" class="input-text" readonly>';
 
       return $html;
   }
}