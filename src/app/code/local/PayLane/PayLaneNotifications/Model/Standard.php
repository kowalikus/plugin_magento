<?php

/**
 * File for PayLane_PayLaneNotifications_Model_Standard class
 *
 * @package		paylane-utils-magento
 * @copyright	2014 PayLane Sp. z o.o.
 * @author		Marcel Tyszkiewicz <marcel.tyszkiewicz@paylane.com>
 */

class PayLane_PayLaneNotifications_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
	/**
	 * Payment transaction notification
	 * @var string
	 */
	const TRANSACTION_TYPE_PAYMENT = 'S';

	/**
	 * Refund transaction notification
	 * @var string
	 */
	const TRANSACTION_TYPE_REFUND = 'R';

	/**
	 * Chargeback transaction notification
	 * @var string
	 */
	const TRANSACTION_TYPE_CHARGEBACK = 'CB';

	/**
	 * Notification data
	 *
	 * @var array
	 */
	protected $notification_data = array();

	/**
	 * Constructor
	 *
	 * @param	array	$notification_data	Notification POST data from PayLane
	 */
	public function __construct(Array $notification_data)
	{
		$this->_code = 'paylanenotifications';
		$this->_canUseCheckout = false;
		$this->_isInitializeNeeded = true;

		$this->notification_data = $notification_data;
	}

	/**
	 * Verifies the token received in the notification.
	 * 
	 * @return bool
	 */
	public function isTokenValid()
	{
		return isset($this->notification_data['token']) &&
				$this->notification_data['token'] === Mage::getStoreConfig('payment/paylanenotifications/token');
	}

	/**
	 * Returns the notification communication ID.
	 * 
	 * @return string
	 */
	public function getCommunicationId()
	{
		return $this->notification_data['communication_id'];
	}

	/**
	 * Handles the notification package data and
	 * updates the store transaction statuses accordingly.
	 * 
	 * @return bool
	 */
	public function handleNotification()
	{
		$correct = true;

		foreach ($this->notification_data['content'] as $content)
		{
			switch ($content['type'])
			{
				case self::TRANSACTION_TYPE_PAYMENT:
					if (!$this->handlePayment($content))
					{
						$correct = false;
					}
					break;
			}
		}

		return $correct;
	}
	/**
	 * Handles a single payment notification message.
	 * 
	 * @param	Array	$payment	Payment array
	 * @return	bool
	 */
	protected function handlePayment(Array $payment)
	{
		$transaction = $this->fetchTransactionByTxnId($payment['id_sale']);
		
		if (!$transaction)
		{
			return false;
		}

		if ((float) $payment['amount'] !== (float) $transaction->getOrderPaymentObject()->getData('amount_ordered'))
		{
			return false;
		}

		if ($transaction->getIsClosed())
		{
			return true;
		}

		$data = array();
		$data['Notification clearing date'] = date('Y-m-d H:i:s');

		if (isset($payment['text']) && strlen($payment['text']))
		{
			$data['Notification description'] = $payment['text'];
		}

		try
		{
			$transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $data);
			$transaction->close();
		}
		catch (Exception $e)
		{
			return false;
		}

		if (!$this->finalizeOrder($transaction->getOrder()))
		{
			return false;
		}

		return true;
	}

	/**
	 * Finalizes the order by marking it as complete, creating an inovice and dispatching a new order email.
	 * 
	 * @param	Mage_Sales_Model_Order	$order	Order object
	 * @return	bool
	 */
	protected function finalizeOrder(Mage_Sales_Model_Order $order)
	{
		$order->setStatus(Mage::getStoreConfig('payment/paylanenotifications/order_status'));
		$order->addStatusHistoryComment("PayLane notification received, the transaction was succesfully processed. Order status updated to complete.");

		try
		{
			if (!$order->canInvoice())
			{
				return false;
			}

			$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

			if (!$invoice->getTotalQty())
			{
				return false;
			}

			$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
			$invoice->register();

			Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();

			$order->sendNewOrderEmail();
			$order->save();
		}
		catch (Mage_Core_Exception $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Fetches the order payment transaction object, based on the gateway transaction ID.
	 * 
	 * @param 	integer		$txn_id	PayLane sale ID
	 * @return	Mage_Sales_Model_Order_Payment_Transaction|bool
	 */
	protected function fetchTransactionByTxnId($txn_id)
	{
		$transactions = Mage::getModel('sales/order_payment_transaction')->getCollection();
		$transactions->getSelect()->where('txn_id = ?', $txn_id);

		$transaction = $transactions->fetchItem();
		if (!$transaction)
		{
			return false;
		}
		
		$transaction->setOrderPaymentObject($transaction->getOrderPaymentObject());

		return $transaction;
	}
}
