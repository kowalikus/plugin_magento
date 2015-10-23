<?php  
/**
  * - Add Paylane Sale ID to order 
  * - Add Card Authorization ID to customer
  * 
  * @author Michał Zabielski <michal.zabielski@orba.pl>
 */
Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));
        
$installer = $this;
 
$installer->startSetup();

$paylaneSaleId  = array(
   'type'          => 'varchar',
   'backend_type'  => 'text',
   'frontend_input' => 'text',
   'is_user_defined' => true,
   'label'         => 'Paylane Sale ID',
   'visible'       => true,
   'required'      => false,
   'user_defined'  => false,
   'searchable'    => false,
   'filterable'    => false,
   'comparable'    => false,
   'default'       => null
);

$cardAuthorizationId  = array(
   'type'          => 'varchar',
   'backend_type'  => 'text',
   'frontend_input' => 'text',
   'is_user_defined' => true,
   'label'         => 'Paylane Card Authorization ID',
   'visible'       => true,
   'required'      => false,
   'user_defined'  => false,
   'searchable'    => false,
   'filterable'    => false,
   'comparable'    => false,
   'default'       => null
);

$installer->addAttribute('order', 'paylane_sale_id', $paylaneSaleId);
$installer->addAttribute('customer', 'card_authorization_id', $cardAuthorizationId);
 
$installer->endSetup();