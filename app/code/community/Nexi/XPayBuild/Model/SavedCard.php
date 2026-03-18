<?php
/**
 * Model for a saved payment card (one-click / stored credential).
 *
 * Maps to table `nexi_saved_cards` via resource model
 * Nexi_XPayBuild_Model_Resource_SavedCard.
 */
class Nexi_XPayBuild_Model_SavedCard extends Mage_Core_Model_Abstract
{
    const GATEWAY_TYPE_XPAY = 'XPAY';

    protected function _construct()
    {
        $this->_init('nexi_xpaybuild/savedCard');
    }

    /**
     * @return int|null
     */
    public function getCustomerId()
    {
        return $this->getData('customer_id');
    }

    /**
     * @return string|null  'XPAY'
     */
    public function getGatewayType()
    {
        return $this->getData('gateway_type');
    }

    /**
     * @return string|null  numeroContratto (XPay)
     */
    public function getGatewayToken()
    {
        return $this->getData('gateway_token');
    }

    /**
     * @return string|null  e.g. '****1234'
     */
    public function getMaskedPan()
    {
        return $this->getData('masked_pan');
    }

    /**
     * @return string|null  e.g. 'VISA', 'MASTERCARD'
     */
    public function getBrand()
    {
        return $this->getData('brand');
    }

    /**
     * @return int|null  1–12
     */
    public function getExpiryMonth()
    {
        return $this->getData('expiry_month') !== null
            ? (int)$this->getData('expiry_month')
            : null;
    }

    /**
     * @return int|null  4-digit year, e.g. 2028
     */
    public function getExpiryYear()
    {
        return $this->getData('expiry_year') !== null
            ? (int)$this->getData('expiry_year')
            : null;
    }

    /**
     * @return int  1 = active, 0 = deleted/deactivated
     */
    public function getIsActive()
    {
        return (int)$this->getData('is_active');
    }

    /**
     * @return string|null  MySQL TIMESTAMP string
     */
    public function getCreatedAt()
    {
        return $this->getData('created_at');
    }

    /**
     * @param int $v
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function setCustomerId($v)
    {
        return $this->setData('customer_id', (int)$v);
    }

    /**
     * @param string $v  'XPAY'
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function setGatewayType($v)
    {
        return $this->setData('gateway_type', $v);
    }

    /**
     * @param string $v
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function setGatewayToken($v)
    {
        return $this->setData('gateway_token', $v);
    }

    /**
     * @param string|null $v
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function setMaskedPan($v)
    {
        return $this->setData('masked_pan', $v);
    }

    /**
     * @param string|null $v
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function setBrand($v)
    {
        return $this->setData('brand', $v);
    }

    /**
     * @param int|null $v  1–12
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function setExpiryMonth($v)
    {
        return $this->setData('expiry_month', $v !== null ? (int)$v : null);
    }

    /**
     * @param int|null $v  4-digit year
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function setExpiryYear($v)
    {
        return $this->setData('expiry_year', $v !== null ? (int)$v : null);
    }

    /**
     * @param int $v  1 = active, 0 = deactivated
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function setIsActive($v)
    {
        return $this->setData('is_active', (int)$v);
    }

    /**
     * Deactivate (soft-delete) this card: sets is_active = 0 and persists.
     *
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function deactivate()
    {
        $this->setIsActive(0);
        $this->save();
        return $this;
    }

    /**
     * Validate required fields before persisting.
     *
     * @return Nexi_XPayBuild_Model_SavedCard
     * @throws Mage_Core_Exception
     */
    protected function _beforeSave()
    {
        parent::_beforeSave();

        /** @var int $customerId */
        $customerId = (int)$this->getCustomerId();
        if ($customerId <= 0) {
            Mage::throwException(
                Mage::helper('nexi_xpaybuild')->__('SavedCard: customer_id must be a positive integer.')
            );
        }

        /** @var string $gatewayType */
        $gatewayType = $this->getGatewayType();
        if ($gatewayType !== self::GATEWAY_TYPE_XPAY) {
            Mage::throwException(
                Mage::helper('nexi_xpaybuild')->__('SavedCard: gateway_type must be XPAY.')
            );
        }

        /** @var string $token */
        $token = $this->getGatewayToken();
        if (empty($token)) {
            Mage::throwException(
                Mage::helper('nexi_xpaybuild')->__('SavedCard: gateway_token must not be empty.')
            );
        }

        return $this;
    }
}
