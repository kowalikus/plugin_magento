<?php
/**
 * Helper class for API Notifications component
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Helper_Notification extends Mage_Core_Helper_Data {
    const LOG_FILE_NAME = 'paylane-notifications.log';
    const XML_CONFIG_LOG_ENABLED = 'payment/paylane_notifications/enable_log';
    const XML_CONFIG_NOTIFICATION_MODE = 'payment/paylane_notifications/notification_mode';
    const XML_CONFIG_NOTIFICATION_TOKEN = 'payment/paylane_notifications/notification_token';
    
    const NOTIFICATION_MODE_MANUAL = 'manual';
    const NOTIFICATION_MODE_AUTO = 'auto';
    
    const NOTIFICATION_TYPE_SALE = 'S';
    const NOTIFICATION_TYPE_REFUND = 'R';
    const NOTIFICATION_TYPE_CHARGEBACK = 'CB';
    
    protected $transactionTypes = array(
        self::NOTIFICATION_TYPE_SALE,
        self::NOTIFICATION_TYPE_REFUND,
        self::NOTIFICATION_TYPE_CHARGEBACK
    );
    
    public function isLogEnabled()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_LOG_ENABLED);
    }
    
    public function isManualMode()
    {
        return (Mage::getStoreConfig(self::XML_CONFIG_NOTIFICATION_MODE) == self::NOTIFICATION_MODE_MANUAL);
    }
    
    public function isAutoMode()
    {
        return (Mage::getStoreConfig(self::XML_CONFIG_NOTIFICATION_MODE) == self::NOTIFICATION_MODE_AUTO);
    }
    
    public function log($msg)
    {
        if($this->isLogEnabled()) {
            Mage::log($msg, null, self::LOG_FILE_NAME);
        }
    }
    
    public function getNotificationToken()
    {
        return Mage::getStoreConfig(self::XML_CONFIG_NOTIFICATION_TOKEN);
    }
}
