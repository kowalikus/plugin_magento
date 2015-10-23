<?php
/**
 * Controller to handle payment flow
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_PaymentController extends Mage_Core_Controller_Front_Action {
    
    /**
     * Controller for test purposes only
     */
    public function indexAction()
    { 
        $helper = Mage::helper('paylane');
        
        echo 'PaymentController | indexAction() works!'; die;
    }
    
    public function redirectAction()
    {   
        $helper = Mage::helper('paylane');
        
        $lastOrderId = Mage::getSingleton('checkout/session')
                   ->getLastRealOrderId();
        $order = Mage::getModel('sales/order')
                   ->loadByIncrementId($lastOrderId);
        $paymentType = $order->getPayment()->getAdditionalInformation('paylane_payment_type');
        
        if($paymentType == Endora_PayLane_Helper_Data::GATEWAY_TYPE_SECURE_FORM) {
            $this->_redirect('paylane/payment/secureForm', array('_secure' => true));
        } else {
            $paymentParams = Mage::getSingleton('checkout/session')->getData('payment_params');
            Mage::getSingleton('checkout/session')->unsetData('payment_params');
            $apiPayment = Mage::getModel('paylane/api_payment_' . $paymentType);
            $result = $apiPayment->handlePayment($order, $paymentParams);

            $this->_redirect($helper->getRedirectUrl(!$result), array('_secure' => true));
        }
    }
    
    public function secureFormAction()
    {
        $lastOrderId = Mage::getSingleton('checkout/session')
                   ->getLastRealOrderId();
        $order = Mage::getModel('sales/order')
                   ->loadByIncrementId($lastOrderId);
        $this->loadLayout();
        $this->getLayout()->getBlock('paylane_redirect')->setOrder($order);
        $this->renderLayout();
    }
    
    public function secureFormResponseAction()
    {
        $helper = Mage::helper('paylane');
        $payment = Mage::getModel('paylane/payment');
        $params = $this->getRequest()->getParams();
        
        $error = false;
        $orderStatus = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $orderIncrementId = $params['description'];
        $transactionId = $payment->getTransactionId($params);
        $order = Mage::getModel('sales/order')->load($orderIncrementId, 'increment_id');
        $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $transactionId);
        
        if($payment->verifyResponseHash($params)) {
            switch($params['status']) {
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_PENDING :
                    $orderStatus = $helper->getPendingOrderStatus();
                    break;
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_PERFORMED :
                    $orderStatus = $helper->getPerformedOrderStatus();
                    break;
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_CLEARED :
                    $orderStatus = $helper->getClearedOrderStatus();
                    break;
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_ERROR :
                default:
                    $orderStatus = $helper->getErrorOrderStatus();
                    $error = true;
            }
        } else {
            $orderStatus = Mage_Core_Sales_Order::STATE_HOLDED;
            $error = true;
        }
        
        if(!empty($params['id_error']) || $error) {
            $errorCode = (!empty($params['error_code'])) ? $params['error_code'] : '';
            $errorText = (!empty($params['error_text'])) ? $params['error_text'] : '';
            $comment = $helper->__('There was an error in payment process via PayLane module (Error ID: %s , Error code: %s, Error text: %s)', $params['id_error'], $errorCode, $errorText);
        } else {
            $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $transactionId);
            $order->setPaylaneSaleId($transactionId);
        }
        
        $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
        $order->save();
        
        $this->_redirect($helper->getRedirectUrl($error), array('_secure' => true));
    }
    
    public function fetchPaymentTemplateAction()
    {
        $paymentType = $this->getRequest()->getParam('paymentType');
        $templatePath = strtolower($paymentType);
        
        echo $this->getLayout()->createBlock('paylane/payment_'.$paymentType)->setTemplate('paylane/payment/'.$templatePath.'.phtml')->toHtml();
    }
    
    public function externalUrlResponseAction()
    {
        $lastOrderId = Mage::getSingleton('checkout/session')
                   ->getLastRealOrderId();
        $order = Mage::getModel('sales/order')
               ->loadByIncrementId($lastOrderId);
        $helper = Mage::helper('paylane');
        $payment = Mage::getModel('paylane/payment');
        $result = $this->getRequest()->getParams();
        $success = false;
        $paymentType = $order->getPayment()->getAdditionalInformation('paylane_payment_type');
        
        $id = '';
        if($result['status'] != Endora_PayLane_Model_Payment::PAYMENT_STATUS_ERROR) {
            $id = $result['id_sale'];
        }
        
//        var_dump($result, $paymentType); die;
        
        if($payment->verifyResponseHash($result, $paymentType)) {
            switch($result['status']) {
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_CLEARED :
                        $orderStatus = $helper->getClearedOrderStatus();
                        $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $result['id_sale']);
                        $order->setPaylaneSaleId($result['id_sale']);
                        $success = true;
                    break;
                
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_PERFORMED :
                        $orderStatus = $helper->getPerformedOrderStatus();
                        $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $result['id_sale']);
                        $order->setPaylaneSaleId($result['id_sale']);
                        $success = true;
                    break;
                
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_ERROR :
                        $orderStatus = $helper->getErrorOrderStatus();
                        $errorCode = '';
                        $errorText = '';
                        if(!empty($result['error'])) {
                            $errorCode = (!empty($result['error']['error_number'])) ? $result['error']['error_number'] : '';
                            $errorText = (!empty($result['error']['error_description'])) ? $result['error']['error_description'] : '';
                        }
                        $comment = $helper->__('There was an error in payment process via PayLane module (Error code: %s, Error text: %s)', $errorCode, $errorText);
                    break;
                
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_PENDING :
                default :
                        $orderStatus = $helper->getPendingOrderStatus();
                        $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $result['id_sale']);
                        $order->setPaylaneSaleId($result['id_sale']);
                        $success = true;
                    break;
            }
        } else {
            $orderStatus = $helper->getErrorOrderStatus();
            $comment = $helper->__('There was an error in payment process via PayLane module (hash verification failed)');
        }
        
        $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
        $order->save();
        
        $this->_redirect($helper->getRedirectUrl(!$success), array('_secure' => true));
    }
    
    public function recurringProfileResponseAction()
    {
        $lastOrderId = Mage::getSingleton('checkout/session')
                   ->getLastRealOrderId();
        $order = Mage::getModel('sales/order')
               ->loadByIncrementId($lastOrderId);
        $helper = Mage::helper('paylane');
        $payment = Mage::getModel('paylane/payment');
        $result = $this->getRequest()->getParams();
        $success = false;
        
        var_dump($result); die;
    }
}