<?php
/**
 * Observer that fires when admin saves payment configuration.
 *
 * Fetches available CC brands from the XPay API and stores them
 * for display in the frontend (card brand logos).
 */
class Nexi_XPayBuild_Model_Observer
{
    /**
     * Triggered on event: admin_system_config_changed_section_payment
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function sectionPaymentChanged(Varien_Event_Observer $observer)
    {
        /** @var Nexi_XPayBuild_Helper_Data $helper */
        $helper = Mage::helper('nexi_xpaybuild');
        $helper->log('Observer::sectionPaymentChanged triggered');

        try {
            $isEnabled = (bool)$helper->getConfig('active');
            $helper->log(sprintf('Observer: enabled=%s', $isEnabled ? 'yes' : 'no'));

            if ($isEnabled) {
                $this->_fetchXpayAvailableMethods($helper);
            }

        } catch (Exception $e) {
            $helper->log(
                'Observer::sectionPaymentChanged error: ' . $e->getMessage(),
                Zend_Log::ERR
            );
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Nexi XPay Build: %s', $e->getMessage())
            );
        }

        // Reinit config to see changes immediately
        Mage::app()->getConfig()->reinit();
    }

    /**
     * Fetch available payment methods from XPay profileInfo API.
     * Stores CC brand logos for display in the checkout form.
     *
     * @param Nexi_XPayBuild_Helper_Data $helper
     * @return void
     */
    protected function _fetchXpayAvailableMethods($helper)
    {
        $alias  = $helper->getXpayAlias();
        $macKey = $helper->getXpayMacKey();

        if (empty($alias) || empty($macKey)) {
            $helper->log('Observer: XPay credentials not configured, skipping profileInfo');
            return;
        }

        $helper->log('Observer: fetching XPay available methods via profileInfo');

        /** @var Nexi_XPayBuild_Model_Api_XpayClient $client */
        $client   = Mage::getModel('nexi_xpaybuild/api_xpayClient');
        $response = $client->profileInfo();

        $urlLogo          = isset($response['urlLogoNexiLarge']) ? $response['urlLogoNexiLarge'] : '';
        $availableMethods = isset($response['availableMethods']) ? $response['availableMethods'] : array();

        Mage::getModel('core/config')->saveConfig(
            'payment/nexi_xpaybuild/url_logo',
            $urlLogo
        );
        Mage::getModel('core/config')->saveConfig(
            'payment/nexi_xpaybuild/available_methods',
            json_encode($availableMethods)
        );

        $helper->log(
            'Observer: XPay available methods saved (' . count($availableMethods) . ' methods, logo=' . $urlLogo . ')'
        );
    }

}
