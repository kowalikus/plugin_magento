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
	 * Return HTTP variable depending on module configuration - via POST or GET
	 *
	 * @param string $name variable name
	 * @return string variable value
	 */
	public function getHttpVariable($name)
	{
		$response_method = Mage::getStoreConfig('payment/paylanecreditcard/response_method');

		if ($response_method == "get")
		{
			return $_GET[$name];
		}
		else
		{
			return $_POST[$name];
		}
	}

	/**
	 * Check response from PayLane Direct System (3-D Secure authorization)
	 */
	public function backAction()
	{
		$paylanecreditcard = Mage::getSingleton("paylanecreditcard/standard");

		if (!is_null($this->getHttpVariable('status')))
		{			
			$status = $this->getHttpVariable('status');
			if (self::STATUS_ERROR === $status)
			{				
				if( !is_null($this->getHttpVariable('error_text')))
				{
					// an error message
			    	$paylanecreditcard->addComment($this->getHttpVariable('error_text'), true);

			    	$this->_redirect('checkout/onepage/failure');
					return;
				}
				else
				{
					Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/failure"));
					return;
				}
			}

			if (!is_null($this->getHttpVariable('id_3dsecure_auth')))
			{
				$id_secure3d_auth = $this->getHttpVariable('id_3dsecure_auth');

				if ($id_secure3d_auth == $paylanecreditcard->getIdSecure3dAuth())
				{
					// connect to PayLane Direct System
					$paylane_client = new PayLaneClient();

					// get login and password from store config
					$direct_login = Mage::getStoreConfig('payment/paylanecreditcard/direct_login');
					$direct_password = Mage::getStoreConfig('payment/paylanecreditcard/direct_password');

					$status = $paylane_client->connect($direct_login, $direct_password);
					if ($status == false)
					{
						// an error message
				    	$paylanecreditcard->addComment("Error connecting to the payment gateway... Please try again later.", false);

				    	session_write_close();
				    	$this->_redirect('checkout/onepage/failure');
						return;
					}

					$result = $paylane_client->saleBy3DSecureAuthorization($id_secure3d_auth);
					if ($result == false)
					{
						// an error message
					   	$paylanecreditcard->addComment("Error processing your payment... Please try again later.", true);

					   	session_write_close();
					   	$this->_redirect('checkout/onepage/failure');
						return;
					}

					if (isset($result->ERROR))
					{
						// an error message
					   	$paylanecreditcard->addComment($result->ERROR->error_description, true);

					   	session_write_close();
					   	$this->_redirect('checkout/onepage/failure');
					   	return;
					}

					if (isset($result->OK))
					{
						$paylanecreditcard->setCurrentOrderPaid();
						$paylanecreditcard->addComment('id_sale=' . $result->OK->id_sale);
						$paylanecreditcard->addTransaction($result->OK->id_sale);

					   	session_write_close();
					   	$this->_redirect('checkout/onepage/success');
					   	return;
					}
					else
					{
						Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
						return;
					}
				}
				else
				{
					Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/checkout/onepage/failure"));
					return;
				}
			}
			else
			{
				Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
				return;
			}
		}
		else
		{
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
			return;
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
		if ($status == false)
		{
			// an error message
	    	$paylanecreditcard->addComment("Error processing your payment... Please try again later.", true, true);

	    	session_write_close();
	    	$this->_redirect('checkout/onepage/failure');
			return;
		}

		$secure3d = Mage::getStoreConfig('payment/paylanecreditcard/secure3d');

		if ($secure3d == true)
		{
			$back_url = Mage::getUrl('paylanecreditcard/standard/back', array('_secure' => true));

			$result = $paylane_client->checkCard3DSecureEnrollment($data, $back_url);

			if ($result == false)
			{
				// an error message
		    	$paylanecreditcard->addComment("Error processing your payment... Please try again later.", true, true);

		    	session_write_close();
		    	$this->_redirect('checkout/onepage/failure');
				return;
			}

			if (isset($result->ERROR))
			{
				// an error message
		    	$paylanecreditcard->addComment($result->ERROR->error_description, true, true);

		    	session_write_close();
		    	$this->_redirect('checkout/onepage/failure');
		    	return;
			}

			if (isset($result->OK))
			{
				$paylanecreditcard->setIdSecure3dAuth($result->OK->secure3d_data->id_secure3d_auth);

				if (isset($result->OK->is_card_enrolled))
				{
					$is_card_enrolled = $result->OK->is_card_enrolled;

					// card is enrolled in 3-D Secure
					if (true == $is_card_enrolled)
					{
						Mage::app()->getFrontController()->getResponse()->setRedirect($result->OK->secure3d_data->paylane_url);
						return;
					}
					// card is not enrolled, perform normal sale
					else
					{
						$data['secure3d'] = array();
						$data['id_secure3d_auth'] = $result->OK->secure3d_data->id_secure3d_auth;

						$result = $paylane_client->multiSale($data);
						if ($result == false)
						{
							// an error message
					    	$paylanecreditcard->addComment("Error processing your payment... Please try again later.", true, true);

					    	session_write_close();
					    	$this->_redirect('checkout/onepage/failure');
							return;
						}

						if (isset($result->ERROR))
						{
							// an error message
					    	$paylanecreditcard->addComment($result->ERROR->error_description, true, true);

					    	session_write_close();
					    	$this->_redirect('checkout/onepage/failure');
					    	return;
						}

						if (isset($result->OK))
						{
							$paylanecreditcard->setCurrentOrderPaid();
							$paylanecreditcard->addComment('id_sale=' . $result->OK->id_sale);
							$paylanecreditcard->addTransaction($result->OK->id_sale);

					    	session_write_close();
					    	$this->_redirect('checkout/onepage/success');
					    	return;
						}
						else
						{
							Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
							return;
						}
					}
				}
				else
				{
					Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
					return;
				}
			}
			else
			{
				Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
				return;
			}
		}
		else
		{
			$result = $paylane_client->multiSale($data);
			if ($result == false)
			{
				// an error message
		    	$paylanecreditcard->addComment("Error processing your payment... Please try again later.", true, true);

		    	session_write_close();
		    	$this->_redirect('checkout/onepage/failure');
				return;
			}

			if (isset($result->ERROR))
			{
		    	$paylanecreditcard->addComment($result->ERROR->error_description, true, true);

		    	session_write_close();
		    	$this->_redirect('checkout/onepage/failure');
		    	return;
			}

			if (isset($result->OK))
			{
				$paylanecreditcard->setCurrentOrderPaid($result->OK->id_sale);

		    	session_write_close();
		    	$this->_redirect('checkout/onepage/success');
		    	return;
			}
			else
			{
				Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl("/"));
				return;
			}
		}
	}
}
