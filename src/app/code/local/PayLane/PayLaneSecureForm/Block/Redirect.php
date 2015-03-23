<?php

/**
 * File for PayLane_PayLaneSecureForm_Block_Redirect class
 *
 * Created on 2011-10-31
 *
 * @package		paylane-utils-magento
 * @copyright	2011 PayLane Sp. z o.o.
 * @author		Michal Nowakowski <michal.nowakowski@paylane.com>
 * @version		SVN: $Id$
 */

class PayLane_PayLaneSecureForm_Block_Redirect extends Mage_Core_Block_Abstract
{
	/**
	 * Generate html code for redirecting page
	 *
	 * @return string html code
	 */
	protected function _toHtml()
	{		
		$paylanesecureform = Mage::getSingleton("paylanesecureform/standard");
		$data = $paylanesecureform->getPaymentData();
		
		$form = new Varien_Data_Form();
		
		$form->setAction("https://secure.paylane.com/order/cart.html");
		$form->setId('paylanesecureform_checkout');
		$form->setName('paylanesecureform_checkout');
		$form->setMethod('POST');
		$form->setUseContainer(true);
		
		foreach ($data as $key => $value)
		{
			$form->addField($key, 'hidden', array('name' => $key, 'value' => $value));
		}
		
		$html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>ut("document.paylanesecureform_checkout.submit();", 2000);</script></head>';
		$html.= 'You are being redirected to PayLane Secure Form...';
		$html.= $form->toHtml();
		$html.= '</body></html>';	
	
		return $html;
	}
}