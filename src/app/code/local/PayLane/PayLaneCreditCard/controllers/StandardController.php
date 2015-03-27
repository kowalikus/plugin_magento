<?php

/**
 * File for PayLane_PayLaneCreditCard_StandardController class
 *
 * Created on 2011-12-01
 *
 * @package		paylane-utils-magento
 * @copyright	2011 PayLane Sp. z o.o.
 * @author		Michal Nowakowski <michal.nowakowski@paylane.com>
 * @version		SVN: $Id$
 */

require_once dirname(__FILE__) . "/../../common/PayLaneClient.php";

class PayLane_PayLaneCreditCard_StandardController extends Mage_Core_Controller_Front_Action
{
	const STATUS_ERROR = "ERROR";

	/** 
	 * Check response from PayLane Direct System (3-D Secure authorization)
	 */
	public function backAction()
	{
		$paylanecreditcard = Mage::getSingleton("paylanecreditcard/standard");

		//values returned from PayLane Secure Form
		$paylane_data = array(
			'status'			=>	$this->getRequest()->getParam('status'),
			'id_3dsecure_auth'	=> $this->getRequest()->getParam('id_3dsecure_auth'),
			'error'				=> array(
				'id_error'			=> $this->getRequest()->getParam('id_error'),
				'error_code'		=>	$this->getRequest()->getParam('error_code'),
				'error_text'		=>	$this->getRequest()->getParam('error_text'),
			),
		);
		
		if (is_null($paylane_data['status']))
		{
			return $this->_redirect('checkout/onepage/failure');
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
				$error_info[] = sprintf('Error description: %s', $paylane_data['error']['error_text']);
			}
			
			$error_message = sprintf("The transaction failed, here's the error information:<br><br>%s", implode('<br>', $error_info));

			$paylanecreditcard->addComment($error_message, false, true);

		    return $this->_redirect('checkout/onepage/failure');
		}

		if (is_null($paylane_data['id_3dsecure_auth']) || $paylane_data['id_3dsecure_auth'] != $paylanecreditcard->getIdSecure3dAuth())
		{
			$paylanecreditcard->addComment('3D-Secure ID mismatch', false, true);

		    return $this->_redirect('checkout/onepage/failure');
		}

		$paylane_client = new PayLaneClient();

		$direct_login = Mage::getStoreConfig('payment/paylanecreditcard/direct_login');
		$direct_password = Mage::getStoreConfig('payment/paylanecreditcard/direct_password');

		$status = $paylane_client->connect($direct_login, $direct_password);
		if (!$status)
		{
	    	$paylanecreditcard->addComment("Error connecting to the payment gateway... Please try again later.", false, true);

	    	return $this->_redirect('checkout/onepage/failure');
		}

		$result = $paylane_client->saleBy3DSecureAuthorization($paylane_data['id_3dsecure_auth']);

		if (isset($result->OK))
		{
			$paylanecreditcard->setCurrentOrderPaid();
			$paylanecreditcard->addComment('PayLane sale ID = ' . $result->OK->id_sale);
			$paylanecreditcard->addTransaction($result->OK->id_sale);

			return $this->_redirect('checkout/onepage/success');
		}
		else
		{
			$error = $this->getErrorMessage($result);

		   	$paylanecreditcard->addComment($error, true, true);

		   	return $this->_redirect('checkout/onepage/failure');
		}
	}

	/**
	 * Perform card payment - call PayLane multiSale or redirect to 3-D Secure Auth Page
	 */
	public function payAction()
	{
		$paylanecreditcard = Mage::getSingleton("paylanecreditcard/standard");
		$data = $paylanecreditcard->getPaymentData();

		if (is_null($data))
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
		}

		// connect to PayLane Direct System
		$paylane_client = new PayLaneClient();

		// get login and password from store config
		$direct_login = Mage::getStoreConfig('payment/paylanecreditcard/direct_login');
		$direct_password = Mage::getStoreConfig('payment/paylanecreditcard/direct_password');

		$status = $paylane_client->connect($direct_login, $direct_password);
		if (!$status)
		{
	    	$paylanecreditcard->addComment("Error connecting to the PayLane gateway... please try again later.", true, true);

	    	return $this->_redirect('checkout/onepage/failure');
		}

		$secure3d = Mage::getStoreConfig('payment/paylanecreditcard/secure3d');

		if ($secure3d == true)
		{
			$back_url = Mage::getUrl('paylanecreditcard/standard/back', array('_secure' => true));

			$result = $paylane_client->checkCard3DSecureEnrollment($data, $back_url);
			if (!$result)
			{
				// an error message
		    	$paylanecreditcard->addComment("Error processing your payment... Please try again later.", true, true);

		    	return $this->_redirect('checkout/onepage/failure');
			}

			if (isset($result->OK))
			{
				$paylanecreditcard->setIdSecure3dAuth($result->OK->secure3d_data->id_secure3d_auth);
				$is_card_enrolled = $result->OK->is_card_enrolled;

				if ($is_card_enrolled)
				{
					return Mage::app()->getFrontController()->getResponse()->setRedirect($result->OK->secure3d_data->paylane_url);
				}

				$data['secure3d'] = array();
				$data['id_secure3d_auth'] = $result->OK->secure3d_data->id_secure3d_auth;

				$result = $paylane_client->multiSale($data);
				if (!$result)
				{
			    	$paylanecreditcard->addComment("Error processing your payment... Please try again later.", true, true);

			    	return $this->_redirect('checkout/onepage/failure');
				}

				if (isset($result->OK))
				{
					$paylanecreditcard->setCurrentOrderPaid();
					$paylanecreditcard->addComment('PayLane sale ID = ' . $result->OK->id_sale);
					$paylanecreditcard->addTransaction($result->OK->id_sale);

			    	return $this->_redirect('checkout/onepage/success');
				}
				else
				{
					$error = $this->getErrorMessage($result);
			    	$paylanecreditcard->addComment($error, true, true);

			    	return $this->_redirect('checkout/onepage/failure');
				}
			}
			else
			{
				$error = $this->getErrorMessage($result);
		    	$paylanecreditcard->addComment($error, true, true);

		    	return $this->_redirect('checkout/onepage/failure');
			}
		}
		else
		{
			$result = $paylane_client->multiSale($data);
			if (!$result)
			{
		    	$paylanecreditcard->addComment("Error processing your payment... Please try again later.", true, true);

		    	return $this->_redirect('checkout/onepage/failure');
			}

			if (isset($result->OK))
			{
				$paylanecreditcard->setCurrentOrderPaid($result->OK->id_sale);

		    	return $this->_redirect('checkout/onepage/success');
			}
			else
			{
				$error = $this->getErrorMessage($result);
		    	$paylanecreditcard->addComment($error, true, true);

		    	return $this->_redirect('checkout/onepage/failure');
			}
		}
	}

	private function getErrorMessage(StdClass $status)
	{
		$parts = array();

		if (isset($status->ERROR))
		{
			if (isset($status->ERROR->id_error))
			{
				$parts[] = sprintf('Sale error ID: %d', $status->ERROR->id_error);
			}
			
			if (isset($status->ERROR->error_number))
			{
				$parts[] = sprintf('Error number: %d', $status->ERROR->error_number);
			}
			
			if (isset($status->ERROR->error_description))
			{
				$parts[] = sprintf('Error descriotion: %s', $status->ERROR->error_description);
			}

			if (!count($parts))
			{
				$parts[] = 'Unable to process transaction';
			}
		}

		return implode('<br>', $parts);
	}
}
