<?php
/**
 * API Notifications handler
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */

class Endora_PayLane_Model_Api_Notification extends Mage_Core_Model_Abstract {
    protected $_client;

    public function __construct() 
    {
        $this->_client = Mage::getModel('paylane/api_client');
        $this->_client->authorize('notifications');
    }
    
    public function getSaleInfo($saleId)
    {
        return $this->_client->getSaleInfo(array('id_sale' => $saleId));
    }
}
