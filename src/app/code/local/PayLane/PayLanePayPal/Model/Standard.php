<?php

/**
 * File for PayLane_PayLanePayPal_Model_Standard class
 *
 * Created on 2011-12-01
 *
 * @package		paylane-utils-magento
 * @copyright	2011 PayLane Sp. z o.o.
 * @author		Michal Nowakowski <michal.nowakowski@paylane.com>
 * @version		SVN: $Id$
 */

class PayLane_PayLanePayPal_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
	/**
	 * Payment method identification name
	 * 
	 * @var string
	 */
	protected $_code = 'paylanepaypal';
	
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
		return Mage::getUrl('paylanepaypal/standard/redirect', array('_secure' => true));
	}
	
	/**
	 * Set current order status to complete and generate invoice for payment
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
		$order->setStatus(Mage::getStoreConfig('payment/paylanepaypal/order_status'));
				
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
	 * Return id_paypal_checkout for current order
	 * 
	 * @return int id_paypal_checkout for current order
	 */
	public function getIdPayPalCheckout()
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
		
		return $payment->getAdditionalInformation('id_paypal_checkout');
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
		$order = Mage::getModel('sales/order');
		$order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
		if (is_null($order))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
		
		$order->addStatusHistoryComment($comment, $send_notification);
		$order->sendNewOrderEmail();
		
		if ($cancel_order)
		{
			$order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);
		}
		
		$order->save();
	}

	/**
	 * Calculates the redirect hash as a security check.
	 * 
	 * @param 	array	$data	Transaction data
	 * @return	string			Calculated hash
	 */
	public function calculateRedirectHash(array $data)
	{
		$payload = implode('|', array(
			Mage::getStoreConfig('payment/paylanepaypal/hash_salt'),
			$data['status'],
			$data['description'],
			$data['amount'],
			$data['currency'],
			$data['transaction_ids']['id_sale'] ?: $data['error']['id_error']
		));
		
		return sha1($payload);
	}
	
	/**
	 * Set id_paypal_checkout for current order
	 * 
	 * @param int $id_paypal_checkout for current order
	 */
	public function setIdPayPalCheckout($id_paypal_checkout)
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
		
		$payment->setAdditionalInformation('id_paypal_checkout', $id_paypal_checkout);
		
		$payment->save();
		$order->save();
	}
	
	/**
	 * Return payment details required for paylane paypal sale
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
		
		if (!is_null($order_data['grand_total']))
		{
			// we need to remove last two 0s, magento stores prices in XX.XX00 format
			$data['amount'] = substr($order_data['grand_total'], 0, -2);
		}
		
		if (!is_null($order_data['order_currency_code']))
		{
			$data['currency_code'] = $order_data['order_currency_code'];
		}

		$description = '';
		
		// generate transaction description - item x quantity
		foreach ($order->getAllItems() as $item)
		{
			$item_data = $item->_data;
			$description .= $item_data['name'] . ' x ' . substr($item_data['qty_ordered'], 0, -5) . '<br>';
			$description .= '; order_id=' . $order_id;
		}
		$data['description'] = substr($description, 0, 200);
		
		return $data;
	}	
}