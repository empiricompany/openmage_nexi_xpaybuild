<?php
abstract class Nexi_XPayBuild_Block_Form_Abstract extends Mage_Payment_Block_Form
{
    /**
     * @return Nexi_XPayBuild_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('nexi_xpaybuild');
    }

    /**
     * Check if the current customer is logged in.
     *
     * @return bool
     */
    public function isCustomerLoggedIn()
    {
        return Mage::getSingleton('customer/session')->isLoggedIn();
    }

    /**
     * Main Nexi logo URL (skin asset).
     *
     * @return string
     */
    public function getNexiLogoUrl()
    {
        return Mage::getDesign()->getSkinUrl('nexi/xpaybuild/images/nexi-logo.png');
    }

    /**
     * Check if OneClick is enabled AND the customer is logged in.
     *
     * @return bool
     */
    public function isOneclickEnabled()
    {
        if (!$this->isCustomerLoggedIn()) {
            return false;
        }
        return $this->_getHelper()->getOneclickEnabled();
    }

    /**
     * Active saved cards for the current customer.
     * Returns empty array if OneClick is not enabled or customer is not logged in.
     *
     * @return Nexi_XPayBuild_Model_SavedCard[]
     */
    public function getSavedCards()
    {
        if (!$this->isOneclickEnabled()) {
            return array();
        }
        $customerId = (int)Mage::getSingleton('customer/session')->getCustomerId();
        return Mage::helper('nexi_xpaybuild/savedCard')
            ->getActiveCards($customerId);
    }

    /**
     * Accepted card-brand logos.
     * Reads from available_methods (populated by Observer after profileInfo API),
     * showing only brands actually enabled in the merchant's contract.
     * Falls back to a hardcoded list when config is empty (first setup).
     *
     * @return array  [['code'=>'VISA','image'=>'url','alt'=>'Visa'], ...]
     */
    public function getCardBrandLogos()
    {
        $json = Mage::getStoreConfig('payment/nexi_xpaybuild/available_methods');
        if ($json) {
            $methods = json_decode($json, true);
            if (is_array($methods)) {
                $logos = array();
                foreach ($methods as $method) {
                    if (!isset($method['type']) || $method['type'] !== 'CC') {
                        continue;
                    }
                    $logos[] = array(
                        'code'  => $method['code'],
                        'image' => !empty($method['image']) ? $method['image'] : (!empty($method['pngImage']) ? $method['pngImage'] : ''),
                        'alt'   => isset($method['description']) ? $method['description'] : $method['code'],
                    );
                }
                if (!empty($logos)) {
                    return $logos;
                }
            }
        }

        // Fallback: basic list when config is not yet populated (first setup)
        return array(
            array('code' => 'VISA',       'image' => Mage::getDesign()->getSkinUrl('nexi/xpaybuild/images/visa.png'),       'alt' => 'Visa'),
            array('code' => 'MASTERCARD', 'image' => Mage::getDesign()->getSkinUrl('nexi/xpaybuild/images/mastercard.png'), 'alt' => 'Mastercard'),
            array('code' => 'MAESTRO',    'image' => Mage::getDesign()->getSkinUrl('nexi/xpaybuild/images/maestro.png'),    'alt' => 'Maestro'),
        );
    }

}
