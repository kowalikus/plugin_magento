<?php
/**
 * Handle redirect with POST data
 * 
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Block_Redirect extends Mage_Core_Block_Template {
    
    public function getFormHtml() {
        $paymentModel = Mage::getModel('paylane/payment');

        $form = new Varien_Data_Form();
        $form->setAction($paymentModel->getGatewayUrl())
                ->setId('paylane_checkout_secureform')
                ->setName('paylane_checkout_secureform')
                ->setMethod('POST')
                ->setUseContainer(true);

        foreach ($paymentModel->preparePostData($this->getOrder()) as $field => $value) {
            $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
        }
        return $form->toHtml();
    }
}