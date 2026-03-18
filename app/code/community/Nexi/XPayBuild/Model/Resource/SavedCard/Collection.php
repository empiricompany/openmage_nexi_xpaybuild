<?php
class Nexi_XPayBuild_Model_Resource_SavedCard_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('nexi_xpaybuild/savedCard', 'nexi_xpaybuild/savedCard');
    }

    /**
     * Filter by customer ID and active status.
     *
     * @param int $customerId
     * @return Nexi_XPayBuild_Model_Resource_SavedCard_Collection
     */
    public function addCustomerFilter($customerId)
    {
        $this->addFieldToFilter('customer_id', (int)$customerId);
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    /**
     * Filter by gateway type ('XPAY').
     *
     * @param string $gatewayType
     * @return Nexi_XPayBuild_Model_Resource_SavedCard_Collection
     */
    public function addGatewayTypeFilter($gatewayType)
    {
        $this->addFieldToFilter('gateway_type', $gatewayType);
        return $this;
    }

    /**
     * Order results by created_at descending (newest first).
     *
     * @return Nexi_XPayBuild_Model_Resource_SavedCard_Collection
     */
    public function setOrderByCreatedAtDesc()
    {
        $this->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC);
        return $this;
    }
}
