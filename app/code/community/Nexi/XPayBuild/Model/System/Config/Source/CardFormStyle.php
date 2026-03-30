<?php
class Nexi_XPayBuild_Model_System_Config_Source_CardFormStyle
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('nexi_xpaybuild');
        return array(
            array(
                'value' => Nexi_XPayBuild_Model_Api_XpayClient::XPAY_CARD_FORM_STYLE_SPLIT,
                'label' => $helper->__('Split (3 separate fields: PAN, Expiry, CVV)'),
            ),
            array(
                'value' => Nexi_XPayBuild_Model_Api_XpayClient::XPAY_CARD_FORM_STYLE_UNIFIED,
                'label' => $helper->__('Unified (single combined form)'),
            ),
        );
    }
}
