<?php

/**
 * File for PayLane_PayLaneCreditCard_Model_Standard class
 *
 * Created on 2011-11-29
 *
 * @package		paylane-utils-magento
 * @copyright	2011 PayLane Sp. z o.o.
 * @author		Michal Nowakowski <michal.nowakowski@paylane.com>
 * @version		SVN: $Id$
 */

class PayLane_PayLaneCreditCard_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
	/**
	 * Payment method identification name
	 * 
	 * @var string
	 */
	protected $_code = 'paylanecreditcard';
	
	/**
	 * Where to find input form to show on checkout page
	 * 
	 * @var string
	 */
	protected $_formBlockType = 'paylanecreditcard/form_standard';

	/**
     * Can use this payment method in administration panel?
     * 
     * @var boolean
     */
    protected $_canUseInternal = true;
 
    /**
     * Can show this payment method as an option on checkout payment page?
     * 
     * @var boolean
     */
    protected $_canUseCheckout = true;
    
    /**
     * Is this payment method a gateway?
     * 
     * @var boolean
     */
    protected $_isGateway = true;
    
	/**
 	 * Return redirect url
 	 * 
 	 * @return string
 	 */
	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl('paylanecreditcard/standard/pay', array('_secure' => true));
	}
	
	/**
	 * Saves data from Credit Card form
	 * 
	 * @see Mage_Payment_Model_Method_Abstract::assignData()
	 * @return PayLane_PayLaneCreditCard_Model_Standard
	 */
	public function assignData($data)
	{
		if (!($data instanceof Varien_Object)) 
		{
			$data = new Varien_Object($data);
		}
		
		$info = $this->getInfoInstance();
		$info->setAdditionalInformation('card_number', $data->getCardNumber());
		$info->setAdditionalInformation('security_code', $data->getSecurityCode());
		$info->setAdditionalInformation('expiration_month', $data->getExpirationMonth());
		$info->setAdditionalInformation('expiration_year', $data->getExpirationYear());
		$info->setAdditionalInformation('name_on_card', $data->getNameOnCard());
		
		return $this;
	}
	
	/**
	 * Validate user data from input form on the checkout page
	 * 
	 * @see Mage_Payment_Model_Method_Abstract::validate()
	 * @return PayLane_PayLaneCreditCard_Model_Standard
	 */
	public function validate()
	{
		parent::validate();

		$info = $this->getInfoInstance();
		
		$card_number = $info->getAdditionalInformation('card_number');
		$security_code = $info->getAdditionalInformation('security_code');
		$expiration_month = $info->getAdditionalInformation('expiration_month');
		$expiration_year = $info->getAdditionalInformation('expiration_year');
		$name_on_card = $info->getAdditionalInformation('name_on_card');
		
		if ( (strlen($card_number) < 13) || (strlen($card_number) > 19) || !is_numeric($card_number))
		{
			$error = $this->_getHelper()->__('Please enter valid card number');
			
			Mage::throwException($error);
		}
		
		if ( ((strlen($security_code) != 3) && (strlen($security_code) != 4)) || !is_numeric($security_code))
		{
			$error = $this->_getHelper()->__('Please enter valid security code');
			
			Mage::throwException($error);
		}
		
		if ( strlen($expiration_month) != 2)
		{
			$error = $this->_getHelper()->__('Please select expiration month');
			
			Mage::throwException($error);
		}
		
		if ( strlen($expiration_year) != 4)
		{
			$error = $this->_getHelper()->__('Please select expiration year');
			
			Mage::throwException($error);
		}
		
		if ( (strlen($name_on_card) < 2 ) || (strlen($name_on_card) > 50))
		{
			$error = $this->_getHelper()->__('Please enter valid name on card');
			
			Mage::throwException($error);
		}

		return $this;
	}
	
	/**
	 * Return id_secure3d_auth for current order
	 * 
	 * @return int id_secure3d_auth for current order
	 */
	public function getIdSecure3dAuth()
	{
		// get order id
		$order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		if (is_null($order_id))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
				
		// get order
		$order = Mage::getModel('sales/order');
		$order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
		if (is_null($order))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
		
		$payment = $order->getPayment();
		if (is_null($payment) || !($payment instanceof Varien_Object))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
			return;
		}
		
		return $payment->getAdditionalInformation('id_secure3d_auth');
	}
	
	/**
	 * Set id_secure3d_auth for current order
	 * 
	 * @param int $id_secure3d_auth for current order
	 */
	public function setIdSecure3dAuth($id_secure3d_auth)
	{
		// get order id
		$order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		if (is_null($order_id))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
				
		// get order
		$order = Mage::getModel('sales/order');
		$order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
		if (is_null($order))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
		
		$payment = $order->getPayment();
		if (is_null($payment) || !($payment instanceof Varien_Object))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
			return;
		}
		
		$payment->setAdditionalInformation('id_secure3d_auth', $id_secure3d_auth);
		
		$payment->save();
		$order->save();
	}
	
	/**
	 * Set current order status to complete, generate invoice for payment
	 * and remove all card details from additional information for payment
	 */
	public function setCurrentOrderPaid($id_sale)
	{
		// get order id
		$order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		if (is_null($order_id))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
				
		// get order
		$order = Mage::getModel('sales/order');
		$order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
		if (is_null($order))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}

		// change order status to complete
		$order->setStatus(Mage::getStoreConfig('payment/paylanecreditcard/order_status'));
				
		try 
		{
			if (!$order->canInvoice())
			{
				Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
			} 
				
			// create payment invoice for this order
			$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice(); 
			
			if (!$invoice->getTotalQty()) 
			{
				Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
			} 
					
			$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
			$invoice->register();
			$transactionSave = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder());
			$transactionSave->save();
		}
		catch (Mage_Core_Exception $e) 
		{ 
		}

		$this->addTransaction($order, $id_sale);
				
		// send notification email and save changes
		$order->addStatusHistoryComment("In PayLane Merchant Panel check if payment was processed correctly id_sale = " . $id_sale);
		$order->sendNewOrderEmail();
		$order->save();
	}

	/**
	 * Adds a transaction to the order.
	 * 
	 * @param	mixed	$trx_id		Payment gateway ID
	 * @param	bool	$is_closed	Is the transaction closed?
	 */
	public function addTransaction($order, $trx_id, $is_closed = true)
	{
		$payment = $order->getPayment();
		
		$payment->setTransactionId($trx_id);
		$payment->setAdditionalInformation('card_number', "");
		$payment->setAdditionalInformation('security_code', "");
		$payment->setAdditionalInformation('expiration_month', "");
		$payment->setAdditionalInformation('expiration_year', "");
		$payment->setAdditionalInformation('name_on_card', "");
		
		if (!$is_closed)
		{
			$payment->setIsClosed(0);
			$payment->setIsTransactionClosed(0);
		}

		$payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT);
		$payment->save();
	}
	
	/**
	 * Return payment details required for paylane card sale
	 * 
	 * @return array Payment details
	 */
	public function getPaymentData()
	{	
		// get order id
		$order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		if (is_null($order_id))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
		
		// get order
		$order = Mage::getModel('sales/order');
		$order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
		if (is_null($order))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
		
		// get order details
		$order_data = $order->_data;
		if (is_null($order_data))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
		
		// get billing address
		$billing_address_data = $order->getBillingAddress()->_data;
		
		// create array with payment details and fill it with data
		$data = array();
		$data['customer'] = array();
		
		if (!is_null($order->getCustomerName()))
		{
			$data['customer']['name'] = $order->getCustomerName();
		}
		
		if (!is_null($billing_address_data['email']))
		{
			$data['customer']['email'] = $billing_address_data['email'];
		}
		
		$data['customer']['address'] = array();
		
		if (!is_null($billing_address_data['street']))
		{
			$data['customer']['address']['street_house'] = str_replace("\n", ", ", $billing_address_data['street']);
		}
		
		if (!is_null($billing_address_data['postcode']))
		{
			$data['customer']['address']['zip'] = $billing_address_data['postcode'];
		}
		
		if (!is_null($billing_address_data['city']))
		{
			$data['customer']['address']['city'] = $billing_address_data['city'];
		}
		
		if (!is_null($billing_address_data['country_id']))
		{
			$data['customer']['address']['country_code'] = $billing_address_data['country_id'];
		}
		
		if (!is_null($billing_address_data['region']))
		{
			$data['customer']['address']['state'] = $billing_address_data['region'];
		}
		
		if (!is_null($order_data['grand_total']))
		{
			// we need to remove last two 0s, magento stores prices in XX.XX00 format
			$data['amount'] = substr($order_data['grand_total'], 0, -2);
		}
		
		if (!is_null($order_data['remote_ip']))
		{
			$data['customer']['ip'] = $order_data['remote_ip'];
		}
		
		if (!is_null($order_data['order_currency_code']))
		{
			$data['currency_code'] = $order_data['order_currency_code'];
		}
		
		$data['payment_method'] = array();
		$data['payment_method']['card_data'] = array();	
		
		$payment = $order->getPayment();
		if (is_null($payment) || !($payment instanceof Varien_Object))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
			return;
		}
		
		$data['payment_method']['card_data']['card_number'] = $payment->getAdditionalInformation('card_number');
		$data['payment_method']['card_data']['card_code'] = $payment->getAdditionalInformation('security_code');		
		$data['payment_method']['card_data']['expiration_month'] = $payment->getAdditionalInformation('expiration_month');
		$data['payment_method']['card_data']['expiration_year'] = $payment->getAdditionalInformation('expiration_year');
		$data['payment_method']['card_data']['name_on_card'] = $payment->getAdditionalInformation('name_on_card');
		
		$data['fraud_check'] = Mage::getStoreConfig('payment/paylanecreditcard/fraud_check');		
		
		$description = '';

		// generate transaction description - item x quantity
		foreach ($order->getAllItems() as $item)
		{
			$item_data = $item->_data;
			$description .= $item_data['name'] . ' x ' . substr($item_data['qty_ordered'], 0, -5) . '<br>';
			$description .= '; order_id=' . $order_id;
		}
		$data['product'] = array();
		$data['product']['description'] = substr($description, 0, 200);
		
		return $data;
	}
	
	/**
	 * Add comment to order history
	 * 
	 * @param	string	$comment 			Comment (i.e. failure description)
	 * @param	bool	$send_notification	Send notification to user?
	 * @param 	bool	$cancel_order		Whether the order should be cancelled
	 */
	public function addComment($comment, $send_notification = false, $cancel_order = false)
	{
		// get order id
		$order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		if (is_null($order_id))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
		
		// get order
		$order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
		if (is_null($order))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
		
		$order->addStatusHistoryComment($comment, $send_notification);
		
		if ($cancel_order)
		{
			$order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);
		}
		else
		{
			$order->sendNewOrderEmail();
		}
		
		$order->save();
	}
}