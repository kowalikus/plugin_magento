<?php

/**
 * File for PayLane_PayLaneSecureForm_Model_Standard class
 *
 * Created on 2011-10-30
 *
 * @package		paylane-utils-magento
 * @copyright	2015 PayLane Sp. z o.o.
 * @author		Michal Nowakowski <michal.nowakowski@paylane.com>
 */

class PayLane_PayLaneSecureForm_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
	/**
	 * Payment method identification name
	 *
	 * @var string
	 */
	protected $_code = 'paylanesecureform';

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
	 * Is initialization needed?
	 *
	 * @var boolean
	 */
	protected $_isInitializeNeeded = true;

	/**
	 * Calculates the redirect hash as a security check.
	 * 
	 * @param 	array	$data	Transaction data
	 * @return	string			Calculated hash
	 */
	public function calculateRedirectHash(array $data)
	{
		$payload = implode('|', array(
			Mage::getStoreConfig('payment/paylanesecureform/hash_salt'),
			$data['status'],
			$data['description'],
			$data['amount'],
			$data['currency'],
			$data['transaction_ids']['id_sale'] ?: $data['transaction_ids']['id_authorization']
		));
		
		return sha1($payload);
	}

	/**
	 * Return payment information to compare with PayLane response
	 *
	 * @param int $order_id Order ID
	 * @return array Payment information
	 */
	public function getOriginalPaymentData($order_id)
	{
		// get order
		$order = Mage::getModel('sales/order')->load($order_id);
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

		$data = array();

		if (!is_null($order_data['entity_id']))
		{
			$data['description'] = $order_data['entity_id'];
		}

		if (!is_null($order_data['grand_total']))
		{
			// we need to remove last two 0s, magento stores prices in XX.XX00 format
			$data['amount'] = substr($order_data['grand_total'], 0, -2);
		}

		if (!is_null($order_data['order_currency_code']))
		{
			$data['currency'] = $order_data['order_currency_code'];
		}

		// get hash salt from config
		$hash_salt = Mage::getStoreConfig('payment/paylanesecureform/hash_salt');
		if ( !is_null($hash_salt))
		{
			// hash for paylane response
			$hash = sha1($hash_salt . "|1|" . $data['description'] . "|" . $data['amount'] . "|" . $data['currency']);
			$data['hash'] = $hash;
		}

		return $data;
	}

	/**
	 * Return payment details required for paylane secure form
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

		// create array for paylane secure form and fill it with data
		$data = array();

		if (!is_null($order->getCustomerName()))
		{
			$data['customer_name'] = $order->getCustomerName();
		}

		if (!is_null($billing_address_data['email']))
		{
			$data['customer_email'] = $billing_address_data['email'];
		}

		if (!is_null($billing_address_data['street']))
		{
			$data['customer_address'] = str_replace("\n", ", ", $billing_address_data['street']);
		}

		if (!is_null($billing_address_data['postcode']))
		{
			$data['customer_zip'] = $billing_address_data['postcode'];
		}

		if (!is_null($billing_address_data['city']))
		{
			$data['customer_city'] = $billing_address_data['city'];
		}

		if (!is_null($billing_address_data['country_id']))
		{
			$data['customer_country'] = $billing_address_data['country_id'];
		}

		if (!is_null($billing_address_data['region']))
		{
			$data['customer_state'] = $billing_address_data['region'];
		}

		if (!is_null($order_data['entity_id']))
		{
			$data['description'] = $order_data['entity_id'];
		}

		if (!is_null($order_data['grand_total']))
		{
			// we need to remove last two 0s, magento stores prices in XX.XX00 format
			$data['amount'] = substr($order_data['grand_total'], 0, -2);
		}

		if (!is_null($order_data['order_currency_code']))
		{
			$data['currency'] = $order_data['order_currency_code'];
		}

		if (!is_null(Mage::getStoreConfig('payment/paylanesecureform/merchant_id')))
		{
			$data['merchant_id'] = Mage::getStoreConfig('payment/paylanesecureform/merchant_id');
		}

		$data['back_url'] = Mage::getUrl('paylanesecureform/standard/back', array('_secure' => true));
		$data['transaction_type'] = 'S';

		// get hash salt from config
		$hash_salt = Mage::getStoreConfig('payment/paylanesecureform/hash_salt');

		// generate hash if salt is defined
		if ( !is_null($hash_salt))
		{
			$hash = sha1($hash_salt . '|' . $data['description'] . '|' . $data['amount'] . '|' . $data['currency'] . '|S');
			$data['hash'] = $hash;
		}

		$language = Mage::getStoreConfig('payment/paylanesecureform/language');
		if (!is_null($language))
		{
			$data['language'] = $language;
		}
		else
		{
			$data['language'] = "en";
		}

		$description = '';

		// generate transaction description - item x quantity
		foreach ($order->getAllItems() as $item)
		{
			$item_data = $item->_data;
			$description .= $item_data['name'] . ' x ' . substr($item_data['qty_ordered'], 0, -5) . '<br>';
			$description .= '; order_id=' . $order_id;
		}
		$data['transaction_description'] = substr($description, 0, 200);

		// check for mandatory fields
		$mandatory_fields = array('merchant_id', 'description', 'amount', 'currency', 'transaction_type', 'back_url',
								  'transaction_description', 'language');

		foreach ($mandatory_fields as $key)
		{
			if (!isset($data[$key]))
			{
				Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/failure"));
			}
		}

		return $data;
	}

	/**
	 * Add comment to order history
	 *
	 * @param string $comment Comment (i.e. failure description)
	 * @param boolean $send_notification Send notification to user?
	 */
	public function addComment($comment, $send_notification = false)
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
		$order->save();
	}

 	/**
 	 * Return redirect url
 	 *
 	 * @return string
 	 */
	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl('paylanesecureform/standard/redirect', array('_secure' => true));
	}
}
