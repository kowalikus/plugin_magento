<?php

/**
 * File for PayLane_PayLanePayPal_StandardController class
 *
 * Created on 2011-11-30
 *
 * @package		paylane-utils-magento
 * @copyright	2011 PayLane Sp. z o.o.
 * @author		Michal Nowakowski <michal.nowakowski@paylane.com>
 * @version		SVN: $Id$
 */

require_once(dirname(__FILE__) . "/../../common/PayLaneClient.php");

class PayLane_PayLanePayPal_StandardController extends Mage_Core_Controller_Front_Action
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
	 * Return HTTP variable depending on module configuration - via POST or GET
	 * 
	 * @param string $name variable name
	 * @return string variable value
	 */
	public function getHttpVariable($name)
	{
		$response_method = Mage::getStoreConfig('payment/paylanepaypal/response_method');
		
		if ($response_method == "get")
		{
			return $this->getRequest()->getParam($name);
		}
		else
		{
			return $this->getRequest()->getPost($name);
		}
	}
	
	/**
	 * Catch and parse response from PayLane Service
	 */
	public function returnAction()
	{
		$paylanepaypal = Mage::getSingleton("paylanepaypal/standard");
		
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

		if (self::STATUS_PERFORMED === $paylane_data['status'] &&
			isset($paylane_data['transaction_ids']['id_sale']) &&
			$paylanepaypal->calculateRedirectHash($paylane_data) === $this->getHttpVariable('hash'))
		{
			$paylanepaypal->setCurrentOrderPaid($paylane_data['transaction_ids']['id_sale']);
		}
		else if (self::STATUS_ERROR === $paylane_data['status'])
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

			$paylanepaypal->addComment($error_message, false, true);

			return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/failure"));	
		}
		else
		{
			return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}
				
		session_write_close(); 
		return $this->_redirect('checkout/onepage/success');
	}
	
	/**
	 * Catch cancel action
	 */
	public function cancelAction()
	{
		Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		return;
	}
	
	/**
	 * Redirect user to PayLane and PayPal secure page
	 */
	public function redirectAction()
	{
		$paylanepaypal = Mage::getSingleton("paylanepaypal/standard");
		$data = $paylanepaypal->getPaymentData();

		$data['return_url'] = Mage::getUrl('paylanepaypal/standard/return');
		$data['cancel_url'] = Mage::getUrl('paylanepaypal/standard/cancel');
		
		// connect to PayLane Direct System		
		$paylane_client = new PayLaneClient();
		
		// get login and password from store config
		$direct_login = Mage::getStoreConfig('payment/paylanepaypal/direct_login');
		$direct_password = Mage::getStoreConfig('payment/paylanepaypal/direct_password');
		
		$status = $paylane_client->connect($direct_login, $direct_password);
		if ($status == false)
		{
			// an error message
	    	$paylanepaypal->addComment("Error processing your payment... Please try again later.", true, true);
	
	    	session_write_close(); 
	    	$this->_redirect('checkout/onepage/failure');
			return;
		}
		
		$result = $paylane_client->paypalSale($data);
		if ($result == false)
		{
			// an error message
		   	$paylanepaypal->addComment("Error processing your payment... Please try again later.", true, true);
					
		   	session_write_close(); 
		   	$this->_redirect('checkout/onepage/failure');
			return;
		}
						
		if (isset($result->ERROR))
		{
			// an error message
		   	$paylanepaypal->addComment($result->ERROR->error_description, true, true);
					
		   	session_write_close(); 
		   	$this->_redirect('checkout/onepage/failure');
		   	return;
		}
						
		if (isset($result->OK))
		{
			$paylanepaypal->setIdPayPalCheckout($result->OK->id_paypal_checkout);
			$paylanepaypal->addComment('id_paypal_checkout=' . $result->OK->id_paypal_checkout);
		   	
		   	Mage::app()->getFrontController()->getResponse()->setRedirect($result->OK->redirect_url);
		   	return;
		}
		else
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
			return;
		}
	}
}