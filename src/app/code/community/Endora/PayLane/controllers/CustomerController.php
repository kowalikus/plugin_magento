<?php
/**
 * Controller to handle settings in customer "My Account" page
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_CustomerController extends Mage_Core_Controller_Front_Action {
    
    public function indexAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }
    
    public function authorizeCreditCardAction()
    {
        $params = $this->getRequest()->getParams();
        $helper = Mage::helper('paylane');
        $customerId = Mage::getSingleton('customer/session')->getId();
        $creditCardModel = Mage::getModel('paylane/api_payment_creditCard');
        $result = $creditCardModel->handleCardAuthorization($customerId, $params['payment_params']);
        $session = Mage::getSingleton('core/session');
        
        if($result) {
            $session->addSuccess($helper->__('Authorization ends successfully'));
        } else {
            $session->addError($helper->__('An error occurs in authorization process - please try again later'));
        }
        
        $this->_redirect('paylane/customer/index');
    }
    
    public function unauthorizeCreditCardAction()
    {
        $customerId = Mage::getSingleton('customer/session')->getId();
        $session = Mage::getSingleton('core/session');
        $helper = Mage::helper('paylane');
        
        try {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            $customer->setCardAuthorizationId(null);
            $customer->save();
            $session->addSuccess($helper->__('Unauthorization process ends successfully'));
        } catch(Exception $e) {
            $session->addError($helper->__('An error occurs in unauthorization process - please try again later'));
        }
        
        $this->_redirect('paylane/customer/index');
    }
    
}