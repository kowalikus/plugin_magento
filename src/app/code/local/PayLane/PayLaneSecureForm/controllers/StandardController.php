<?php

/**
 * File for PayLane_PayLaneSecureForm_StandardController class
 *
 * Created on 2011-10-30
 *
 * @package		paylane-utils-magento
 * @copyright	2015 PayLane Sp. z o.o.
 * @author		Michal Nowakowski <michal.nowakowski@paylane.com>
 */

class PayLane_PayLaneSecureForm_StandardController extends Mage_Core_Controller_Front_Action
{
	// PayLane transaction statuses
	const STATUS_PERFORMED = "PERFORMED";
	const STATUS_PENDING = "PENDING";
	const STATUS_CLEARED = "CLEARED";
	const STATUS_ERROR = "ERROR";

	/**
	 * List of PayLane notification statuses in the GET/POST response.
	 * @var array
	 */
	private static $transaction_statuses = array(
		self::STATUS_ERROR,
		self::STATUS_CLEARED,
		self::STATUS_PENDING,
		self::STATUS_PERFORMED,
	);

	/**
	 * Redirect user to PayLane Secure Form
	 */
	public function redirectAction()
	{
		$this->getResponse()->setBody($this->getLayout()->createBlock('paylanesecureform/redirect')->toHtml());
	}

	/**
	 * Check PayLane response and change order status
	 */
	public function backAction()
	{
		$paylanesecureform = Mage::getSingleton("paylanesecureform/standard");

		//values returned from PayLane Secure Form
		$paylane_data = array(
			'status'			=>	$this->getRequest()->getParam('status'),
			'description'		=>	$this->getRequest()->getParam('description'),
			'amount'			=>	$this->getRequest()->getParam('amount'),
			'currency'			=>	$this->getRequest()->getParam('currency'),
			'hash'				=>	$this->getRequest()->getParam('hash'),
			'transaction_ids'	=> array(
				'id_sale'			=> $this->getRequest()->getParam('id_sale'),
				'id_authorization'	=> $this->getRequest()->getParam('id_authorization'),
			),
			'error'				=> array(
				'id_error'			=> $this->getRequest()->getParam('id_error'),
				'error_code'		=>	$this->getRequest()->getParam('error_code'),
				'error_text'		=>	$this->getRequest()->getParam('error_text'),
			),
		);
		
		if (is_null($paylane_data['status']) || !in_array($paylane_data['status'], self::$transaction_statuses) ||
			is_null($paylane_data['amount']) || is_null($paylane_data['currency']) ||
			is_null($paylane_data['hash']) || is_null($paylane_data['description']))
		{
			return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/failure"));
		}

		$order = Mage::getModel('sales/order')->loadByIncrementId($paylane_data['description']);

		if (is_null($order))
		{
			return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}

		if (self::STATUS_ERROR === $paylane_data['status'])
		{
			$error_info = array();

			if (isset($paylane_data['error']['id_error']))
			{
				$error_info[] = sprintf('ID sale error: %d', $paylane_data['error']['id_error']);
			}

			if (isset($paylane_data['error']['error_code']))
			{
				$error_info[] = sprintf('Error code: %d', $paylane_data['error']['error_code']);
			}
			
			if (isset($paylane_data['error']['error_text']))
			{
				$error_info[] = sprintf('Error description: %s', $paylane_data['error']['error_code']);
			}
			
			$error_message = sprintf("The transaction failed, here's the error information:<br><br>%s", implode('<br>', $error_info));

			// will be changed later if necessary
			$order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);
			$order->addStatusHistoryComment($error_message);
			$order->save();

			return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/failure"));
		}

		// get original order details
		$original_data = $paylanesecureform->getOriginalPaymentData($paylane_data['description']);

		// check merchant_transaction_id, amount, currency code
		if ($original_data['description'] != $paylane_data['description'] ||
		    $original_data['amount'] != $paylane_data['amount'] ||
			$original_data['currency'] != $paylane_data['currency'])
		{
			return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/failure"));
		}

		// compare hash
		if ($paylane_data['hash'] != $paylanesecureform->calculateRedirectHash($paylane_data))
		{
			return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/failure"));
		}

		$payment = $order->getPayment();
		$payment->setTransactionId($paylane_data['transaction_ids']['id_sale']);

		// set processing status if payment is pending
		if (self::STATUS_PENDING === $paylane_data['status'])
		{
			$payment->setIsClosed(0);
			$payment->setIsTransactionClosed(0);
			$order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
			$order->addStatusHistoryComment("This sale is now being processed by PayLane. To monitor current sale status please login to PayLane Merchant Panel. id_sale = " . $paylane_data['transaction_ids']['id_sale']);
		}
		else
		{
			// ok, now everything is correct, we can change order status
			$order->setStatus(Mage::getStoreConfig('payment/paylanesecureform/order_status'));

			try
			{
				if (!$order->canInvoice())
				{
					Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
				}

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

			$order->sendNewOrderEmail();
			$order->addStatusHistoryComment("In PayLane Merchant Panel check if payment was processed correctly id_sale = " . $paylane_data['transaction_ids']['id_sale']);
		}

		$payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT);

		$payment->save();
		$order->save();

		return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/success"));
	}
}