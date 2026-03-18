<?php
class Nexi_XPayBuild_Block_Form_Build extends Nexi_XPayBuild_Block_Form_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('nexi/xpaybuild/form/build.phtml');
    }

    /**
     * XPay Build SDK JavaScript URL.
     *
     * @return string
     */
    public function getSdkUrl()
    {
        return $this->_getHelper()->getXpaySdkUrl();
    }

    /**
     * Card form embedding style for XPay Build (SPLIT_CARD | CARD).
     *
     * @return string
     */
    public function getCardFormStyle()
    {
        return $this->_getHelper()->getCardFormStyle();
    }

    /**
     * URL to nexi_payment.js (skin asset).
     * Overrides parent to always return the Nexi payment JS.
     *
     * @param  string $fileName  Ignored (kept for parent signature compatibility)
     * @return string
     */
    public function getJsUrl($fileName = '')
    {
        return Mage::getDesign()->getSkinUrl('nexi/xpaybuild/js/nexi_payment.js');
    }

    /**
     * JSON configuration for NexiPaymentXPay JS initialization.
     *
     * @return string
     */
    public function getJsConfig()
    {
        return json_encode(array(
            'methodCode'        => 'nexi_xpaybuild',
            'environment'       => $this->_getHelper()->getXpayEnvironment(),
            'paymentSessionUrl' => (string) Mage::getUrl('nexixpaybuild/xpay/getPaymentData'),
            'saveNonceUrl'      => (string) Mage::getUrl('nexixpaybuild/xpay/saveXpayNonce'),
            'oneClick'          => (bool) $this->isOneclickEnabled(),
            'oneClickCvv'       => false,
            'isLoggedIn'        => (bool) $this->isCustomerLoggedIn(),
        ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }
}
