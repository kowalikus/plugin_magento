<?php
/**
 * Observer model
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Observer {
    
    /**
     * Setting payment type parameters to be used in
     * payment type model
     * 
     * @param type $observer
     */
    public function preparePaymentTypeFormData($observer)
    {   
        $data = Mage::app()->getRequest()->getParams();
        if(!empty($data['payment_params'])) {
            Mage::getSingleton('checkout/session')->setData('payment_params', $data['payment_params']);
        }
    }
    
    /**
     * Cron job method that charge recurring profiles according
     * to their settings
     * 
     * @param Mage_Cron_Model_Schedule $schedule
     */
    public function handleRecurringProfileCron(Mage_Cron_Model_Schedule $schedule)
    {	
        $resourceModel = Mage::getSingleton('core/resource');
        $tableName = $resourceModel->getTableName('sales_recurring_profile');
                
        $query = 'SELECT
                        CASE rpt.period_unit
                                WHEN "day"              THEN FLOOR(DATEDIFF(NOW(), rpt.updated_at) / rpt.period_frequency)
                                WHEN "week" 		THEN FLOOR(FLOOR(DATEDIFF(NOW(), rpt.updated_at) / 7) / rpt.period_frequency)
                                WHEN "semi_month" 	THEN FLOOR(FLOOR(DATEDIFF(NOW(), rpt.updated_at) / 14) / rpt.period_frequency)
                                WHEN "month" 		THEN FLOOR(PERIOD_DIFF(DATE_FORMAT(NOW(), "%Y%m"), DATE_FORMAT(rpt.updated_at, "%Y%m")) - (DATE_FORMAT(NOW(), "%d") < DATE_FORMAT(rpt.updated_at, "%d")) / rpt.period_frequency)
                                WHEN "year" 		THEN FLOOR(YEAR(NOW()) - YEAR(rpt.updated_at) - (DATE_FORMAT(NOW(), "%m%d") < DATE_FORMAT(rpt.updated_at, "%m%d")) / rpt.period_frequency)
                        END
                        AS billing_count,
                        rpt.*
                FROM '.$tableName.' AS rpt
                WHERE
                        rpt.state = "'.Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE.'" AND
                        rpt.updated_at <= NOW() AND
                        rpt.start_datetime <= NOW() AND
                        rpt.method_code = "paylane" AND
                        NOW() >= CASE rpt.period_unit
                                WHEN "day" 		THEN DATE_ADD(rpt.updated_at, INTERVAL rpt.period_frequency DAY)
                                WHEN "week" 		THEN DATE_ADD(rpt.updated_at, INTERVAL rpt.period_frequency WEEK)
                                WHEN "semi_month" 	THEN DATE_ADD(rpt.updated_at, INTERVAL (rpt.period_frequency * 2) WEEK)
                                WHEN "month" 		THEN DATE_ADD(rpt.updated_at, INTERVAL rpt.period_frequency MONTH)
                                WHEN "year" 		THEN DATE_ADD(rpt.updated_at, INTERVAL rpt.period_frequency YEAR)
                        END';

        $connection = $resourceModel->getConnection('core_read');
        $queryResult = $connection->fetchAll($query);
        
        foreach ($queryResult as $profileData) {
            $profile = Mage::getModel('sales/recurring_profile')->addData($profileData);
            $billingCounter = count($profile->getResource()->getChildOrderIds($profile));
            
            if ($profile->getInitAmount()) {
                $billingCounter--;
            }

            if ($profile->getBillFailedLater()){
                    $billingCounter = $this->_multiChargeRecurringProfile($profile, $billingCounter);
            } else {
                    $billingCounter = $this->_singleChargeRecurringProfile($profile, $billingCounter);
            }
        }
    }
    
    /**
     * Doing multiple charges to recurring profile
     * 
     * @param Mage_Sales_Model_Recurring_Profile $profile
     * @param type $billingCounter
     * @return integer Number of charged orders
     */
    protected function _multiChargeRecurringProfile(Mage_Sales_Model_Recurring_Profile $profile, $billingCounter)
    {   
        for ($i = 0; $i < $profile->getBillingCount(); $i++){            
            $billingCounter = $this->_singleChargeRecurringProfile($profile, $billingCounter);
            
            if ($billingCounter >= $profile->getPeriodMaxCycles()){
                break;
            }
        }
        
        return $billingCounter;
    }
    
    /**
     * Doing single charge to recurring profile
     * 
     * @param Mage_Sales_Model_Recurring_Profile $profile
     * @param type $billingCounter
     * @return integer Number of charged orders
     */
    protected function _singleChargeRecurringProfile(Mage_Sales_Model_Recurring_Profile $profile, $billingCounter)
    {
        $paymentModel = Mage::getModel('paylane/payment');
        
        if ($paymentModel->chargeRecurringProfile($profile)) {
            $billingCounter++;
        }

        if ($billingCounter >= $profile->getPeriodMaxCycles()) {
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
        }
        
        return $billingCounter;
    }
}
