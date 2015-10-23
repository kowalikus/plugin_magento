<?php
/**
 * API Resales handler
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */

class Endora_PayLane_Model_Api_Resale extends Mage_Core_Model_Abstract {
    protected $_client;

    public function __construct() 
    {
        $this->_client = Mage::getModel('paylane/api_client');
        $this->_client->authorize('notifications');
    }
    
    public function resaleBySaleId($saleId, Mage_Sales_Model_Order $order)
    {
        $saleArray = array('id_sale' => $saleId);
        return $this->_resale($saleArray, $order);
    }
    
    public function resaleByAuthorization($authorizationId, Mage_Sales_Model_Order $order)
    {
        $authorizationArray = array('id_authorization' => $authorizationId);
        return $this->_resale($authorizationArray, $order);
    }
    
    protected function _resale($transactionArray, Mage_Sales_Model_Order $order)
    {
        $resaleParams = array(
            'amount'      => sprintf('%01.2f', $order->getGrandTotal()),
            'currency'    => $order->getOrderCurrencyCode(),
            'description' => 'Recurring sale for order #' . $order->getIncrementId(),
        );
        
        $resaleParams = array_merge($resaleParams, $transactionArray);
        
        $result = $this->_client->resaleBySale($resaleParams);
        
        return $this->_client->isSuccess();
    }
}
