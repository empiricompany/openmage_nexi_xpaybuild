<?php
class Nexi_XPayBuild_Helper_SavedCard extends Mage_Core_Helper_Abstract
{
    /**
     * Generate a unique XPay numeroContratto (max 30 characters).
     *
     * Format: C{customerId}-{ms4}
     * where ms4 is the last 4 digits of the current millisecond timestamp,
     * used to avoid collisions if the same customer saves a card twice
     * in rapid succession.
     *
     * Examples: C45-3456, C1234-0012
     *
     * @param int $customerId
     * @return string  Max 30 characters
     */
    public function generateXpayContractNumber($customerId)
    {
        $ms4 = str_pad((string)((int)(microtime(true) * 1000) % 10000), 4, '0', STR_PAD_LEFT);
        $raw = 'C' . (int)$customerId . '-' . $ms4;
        return substr($raw, 0, 30);
    }

    /**
     * Persist a card record after a successful payment.
     *
     * Uses an atomic INSERT … ON DUPLICATE KEY UPDATE via Zend_Db's
     * insertOnDuplicate() to avoid the TOCTOU race condition that occurs
     * with the SELECT-then-INSERT pattern.
     *
     * The UNIQUE index on (customer_id, gateway_token) guarantees that:
     *   - New cards are inserted normally.
     *   - Existing cards (even soft-deleted ones) are reactivated and
     *     their display metadata is refreshed.
     *
     * @param int         $customerId
     * @param string      $gatewayType   'XPAY'
     * @param string      $gatewayToken  numeroContratto (XPay)
     * @param string|null $maskedPan     Masked PAN, e.g. '****1234'
     * @param string|null $brand         Card brand, e.g. 'VISA'
     * @param int|null    $expiryMonth   1–12
     * @param int|null    $expiryYear    4-digit year
     * @return Nexi_XPayBuild_Model_SavedCard
     */
    public function saveCard(
        $customerId,
        $gatewayType,
        $gatewayToken,
        $maskedPan,
        $brand,
        $expiryMonth,
        $expiryYear
    ) {
        $helper = Mage::helper('nexi_xpaybuild');

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $write    = $resource->getConnection('core_write');
        $table    = $resource->getTableName('nexi_xpaybuild/saved_card');

        $data = array(
            'customer_id'   => (int) $customerId,
            'gateway_type'  => $gatewayType,
            'gateway_token' => $gatewayToken,
            'masked_pan'    => $maskedPan,
            'brand'         => $brand,
            'expiry_month'  => $expiryMonth,
            'expiry_year'   => $expiryYear,
            'is_active'     => 1,
        );

        // Atomic upsert: INSERT … ON DUPLICATE KEY UPDATE
        // If (customer_id, gateway_token) already exists, reactivate + refresh card details.
        $write->insertOnDuplicate(
            $table,
            $data,
            array('masked_pan', 'brand', 'expiry_month', 'expiry_year', 'gateway_type', 'is_active')
        );

        // Load and return the card record
        /** @var Nexi_XPayBuild_Model_SavedCard $card */
        $card = Mage::getModel('nexi_xpaybuild/savedCard')
            ->getCollection()
            ->addFieldToFilter('customer_id',   (int) $customerId)
            ->addFieldToFilter('gateway_token', $gatewayToken)
            ->setPageSize(1)
            ->getFirstItem();

        $helper->log(sprintf(
            'SavedCard::saveCard() customer=%d token=...%s brand=%s → id=%s',
            $customerId,
            substr($gatewayToken, -4),
            $brand,
            $card->getId() ?: 'null'
        ));

        return $card;
    }

    /**
     * Retrieve all active saved cards for a customer.
     *
     * @param int         $customerId
     * @param string|null $gatewayType  'XPAY' or null for all
     * @return Nexi_XPayBuild_Model_SavedCard[]
     */
    public function getActiveCards($customerId, $gatewayType = null)
    {
        /** @var Nexi_XPayBuild_Model_Resource_SavedCard_Collection $collection */
        $collection = Mage::getModel('nexi_xpaybuild/savedCard')
            ->getCollection()
            ->addCustomerFilter($customerId)
            ->setOrderByCreatedAtDesc();

        if ($gatewayType !== null) {
            $collection->addGatewayTypeFilter($gatewayType);
        }

        return $collection->getItems();
    }

    /**
     * Load a single card by ID, verifying customer ownership.
     *
     * Returns null if the card does not exist, is inactive, or belongs to a
     * different customer.
     *
     * @param int $cardId
     * @param int $customerId
     * @return Nexi_XPayBuild_Model_SavedCard|null
     */
    public function loadCard($cardId, $customerId)
    {
        /** @var Nexi_XPayBuild_Model_SavedCard $card */
        $card = Mage::getModel('nexi_xpaybuild/savedCard')->load((int)$cardId);

        if (!$card->getId()) {
            return null;
        }

        if ((int)$card->getCustomerId() !== (int)$customerId) {
            Mage::helper('nexi_xpaybuild')->log(
                'SavedCard: unauthorised load attempt — card ' . $cardId
                . ' belongs to customer ' . $card->getCustomerId()
                . ', requested by customer ' . $customerId
            );
            return null;
        }

        if (!$card->getIsActive()) {
            return null;
        }

        return $card;
    }

    /**
     * Delete a saved card by marking is_active = 0 in the database.
     *
     * XPay has no API deletion endpoint, so this is a local-only soft-delete.
     *
     * @param int $cardId
     * @param int $customerId
     * @return bool  true on success (card found and deactivated), false otherwise
     */
    public function deleteCard($cardId, $customerId)
    {
        /** @var Nexi_XPayBuild_Model_SavedCard|null $card */
        $card = $this->loadCard($cardId, $customerId);

        if ($card === null) {
            Mage::helper('nexi_xpaybuild')->log(
                'SavedCard::deleteCard — card ' . $cardId
                . ' not found or not owned by customer ' . $customerId
            );
            return false;
        }

        $card->deactivate();

        Mage::helper('nexi_xpaybuild')->log(
            'SavedCard: deactivated card id=' . $card->getId()
            . ' customer=' . $customerId
        );

        return true;
    }

}
