<?php
/**
 * Controller to handle notification sent from PayLane
 * 
 * @link http://devzone.paylane.pl/powiadomienia-o-transakcjach/
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_NotificationController extends Mage_Core_Controller_Front_Action {
    
    public function handleManualAction()
    {
        if(Mage::helper('paylane/notification')->isManualMode()) {
            $helper = Mage::helper('paylane');
            $this->_log('--- START HANDLING NOTIFICATIONS PROCESS - MANUAL MODE ---');
            $notification = Mage::getModel('paylane/api_notification');
            $this->_log('Preparing order collection...');
            $orders = $this->_prepareOrderCollection();
            $this->_log('Order collection prepared');

            foreach($orders as $order) {
                $this->_log('Handle order #' . $order->getIncrementId());
                $saleId = $order->getPaylaneSaleId();
                $this->_log('Paylane Sale ID: ' . $saleId);
                if(!is_null($saleId)) {
                    $result = $notification->getSaleInfo($saleId);
                    if($result['success'] && $saleId == $result['id_sale']) {
                        if($result['status'] == Endora_PayLane_Model_Payment::PAYMENT_STATUS_PERFORMED
                                && $order->getStatus() != $helper->getPerformedOrderStatus()) {
                            $orderStatus = $helper->getPerformedOrderStatus();
                            $comment = $helper->__('Order status changed via PayLane module');
                            $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
                            $order->save();
                            $this->_log('Changed order status to: ' . $orderStatus . ', ('.Endora_PayLane_Model_Payment::PAYMENT_STATUS_PERFORMED.' in PayLane)');
                        } else if($result['status'] == Endora_PayLane_Model_Payment::PAYMENT_STATUS_CLEARED
                                && $order->getStatus() != $helper->getClearedOrderStatus()) {
                            $orderStatus = $helper->getClearedOrderStatus();
                            $comment = $helper->__('Order status changed via PayLane module');
                            $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
                            $order->save();
                            $this->_log('Changed order status to: ' . $orderStatus . ', ('.Endora_PayLane_Model_Payment::PAYMENT_STATUS_CLEARED.' in PayLane)');
                        } else {
                            $this->_log('No changes needed');
                        }
                    }
                } else {
                    $this->_log('Paylane Sale ID is NULL, skip order');
                }
            }
            $this->_log('--- STOP HANDLING NOTIFICATIONS PROCESS - MANUAL MODE ---');
            echo '--- HANDLING NOTIFICATIONS PROCESS COMPLETE - MANUAL MODE ---';
        } else {
            die('--- NOTIFICATIONS PROCESS IN MANUAL MODE IS DISABLED ---');
        }
    }
    
    public function handleAutoAction()
    {
        $notificationHelper = Mage::helper('paylane/notification');
        $notificationModel = Mage::getModel('paylane/api_notification'); //needed to initialize $_SERVER variables
        $helper = Mage::helper('paylane');
        
        if($notificationHelper->isAutoMode()) {
            if(!empty($helper->getNotificationsUsername()) && !empty($helper->getNotificationsPassword())) {
                if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) { 
                    $this->_failAuthorization();
                }
                
                if($helper->getNotificationsUsername() != $_SERVER['PHP_AUTH_USER'] || 
                $helper->getNotificationsPassword() != $_SERVER['PHP_AUTH_PW']) {
                    $this->_failAuthorization();
                }
            }
            
            $this->_log('--- START HANDLING NOTIFICATIONS PROCESS - AUTO MODE ---');
            $params = $this->getRequest()->getParams();
            
            $this->_log($params);
            
            if (empty($params['communication_id'])) {
                $msg = 'Empty communication id';
                $this->_log($msg);
                die($msg);
            }
            
            if (!empty($params['token']) && ($notificationHelper->getNotificationToken() !== $params['token'])) {
                $msg = 'Wrong token';
                $this->_log($msg);
                die($msg);
            }
            
            $messages = $params['content'];
            
            $this->_handleAutoMessages($messages);

            $this->_log('--- STOP HANDLING NOTIFICATIONS PROCESS - AUTO MODE ---');
            $this->_log($params['communication_id']);
            die($params['communication_id']);
        } else {
            $msg = '--- NOTIFICATIONS PROCESS IN AUTO MODE IS DISABLED ---';
            $this->_log($msg);
            die($msg);
        }
    }
    
    protected function _failAuthorization()
    {
        // authentication failed 
        header("WWW-Authenticate: Basic realm=\"Secure Area\""); 
        header("HTTP/1.0 401 Unauthorized"); 
        exit();
    }
    
    protected function _log($msg)
    {
        Mage::helper('paylane/notification')->log($msg);
    }
    
    protected function _prepareOrderCollection()
    {
        $paymentCode = Mage::getModel('paylane/payment')->getCode();
        $orders = Mage::getModel('sales/order')->getCollection()
                    ->join(
                        array('payment' => 'sales/order_payment'),
                        'main_table.entity_id=payment.parent_id',
                        array('payment.method' => 'payment.method')
                    );
        $orders->addFieldToFilter('payment.method', array('eq' => $paymentCode))
                ->setOrder('increment_id','DESC');
        
        return $orders;
    }
    
    protected function _handleAutoMessages($messages)
    {
        $helper = Mage::helper('paylane');
        foreach($messages as $message) {
            if(empty($message['text'])) {
                $this->_log('Message without Magento increment ID - skip');
                continue;
            }

            $this->_log('Handle message for PayLane Sale ID ' . $message['id_sale'] . ', order #' . $message['text']);
            //$order = $helper->getOrderByPaylaneSaleId($message['id_sale']);
            $order = Mage::getModel('sales/order')->load($message['text'], 'increment_id');
            $this->_log('Fetch order #' . $order->getIncrementId());
            
            if(!empty($order)) {
                switch($message['type']) {
                    case Endora_PayLane_Helper_Notification::NOTIFICATION_TYPE_SALE :
                        $orderStatus = $helper->getClearedOrderStatus();
                        $comment = $helper->__('Order status changed via PayLane module');
                        $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
                        $order->save();
                        $this->_log('Changed order status to: ' . $orderStatus . ', ('.Endora_PayLane_Model_Payment::PAYMENT_STATUS_CLEARED.' in PayLane)');
                        break;

                    case Endora_PayLane_Helper_Notification::NOTIFICATION_TYPE_REFUND :
                        try { //do offline refund because it is already done on the PayLane side
                            $data = array(
                                'do_offline' => 1,
                                'shipping_amount' => 0,
                                'adjustment_positive' => $message['amount'],
                                'adjustment_negative' => 0
                            );
                            $this->_handleRefund($order, $data);
                            $order->addStatusHistoryComment($helper->__('Refund was handled via PayLane module | Refund ID: %s', $message['id']));
                            $order->save();
                            $this->_log('Order #' . $order->getIncrementId() . ' was refunded to amout ' . $message['amount']);
                        } catch (Exception $e) {
                            $this->_log('There was an error in refunding: ' . $e->getMessage());
                        }
                        break;

                    default :
                        $msg = 'Unrecognized message type (' . $message['type'] . ')';
                        $this->_log($msg);
                        die($msg);
                        break;
                }
            } else {
                $this->_log('Order with PayLane Sale ID ' . $message['id_sale'] . ' doesn\'t exist - skip');
            }
        }
    }
    
    protected function _handleRefund(Mage_Sales_Model_Order $order, $data)
    {
        try {
            $creditmemo = $this->_initCreditmemo($order, $data);
            if ($creditmemo) {
                if (($creditmemo->getGrandTotal() <=0) && (!$creditmemo->getAllowZeroGrandTotal())) {
                    Mage::throwException(
                        $this->__('Credit memo\'s total must be positive.')
                    );
                }

                $this->_saveCreditmemo($creditmemo);
                return;
            } else {
                Mage::throwException(
                    $this->__('Credit memo\'s not created')
                );
            }
        } catch (Mage_Core_Exception $e) {
            Mage::throwException($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException(
                $this->__('Cannot save the credit memo.')
            );
        }
    }
    
    /**
     * Initialize creditmemo model instance
     *
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    protected function _initCreditmemo(Mage_Sales_Model_Order $order, $data = array(), $update = false)
    {
        $creditmemo = false;
        
        if ($order) {
            $invoice = $this->_initInvoice($order);

            if (!$order->_canCreditmemo($order)) {
                return false;
            }

            $qtys = array();
            $data['qtys'] = $qtys;

            $service = Mage::getModel('sales/service_order', $order);
            if ($invoice) {
                $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);
            } else {
                $creditmemo = $service->prepareCreditmemo($data);
            }

            /**
             * Process back to stock flags
             */
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                $creditmemoItem->setBackToStock(false);
            }
        }

        return $creditmemo;
    }
    
    /**
     * Save creditmemo and related order, invoice in one transaction
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     */
    protected function _saveCreditmemo($creditmemo)
    {
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($creditmemo)
            ->addObject($creditmemo->getOrder());
        if ($creditmemo->getInvoice()) {
            $transactionSave->addObject($creditmemo->getInvoice());
        }
        $transactionSave->save();

        return $this;
    }
    
    /**
     * Initialize requested invoice instance
     * @param unknown_type $order
     */
    protected function _initInvoice($order)
    {
        $invoiceId = $this->getRequest()->getParam('invoice_id');
        if ($invoiceId) {
            $invoice = Mage::getModel('sales/order_invoice')
                ->load($invoiceId)
                ->setOrder($order);
            if ($invoice->getId()) {
                return $invoice;
            }
        }
        return false;
    }
}