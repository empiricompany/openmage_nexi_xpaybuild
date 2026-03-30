<?php
class Nexi_XPayBuild_Model_System_Config_Source_AccountingType
{
    public function toOptionArray()
    {
        return array(
            array('value' => Nexi_XPayBuild_Model_Api_XpayClient::XPAY_TCONTAB_IMMEDIATE, 'label' => Mage::helper('nexi_xpaybuild')->__('Immediata')),
            array('value' => Nexi_XPayBuild_Model_Api_XpayClient::XPAY_TCONTAB_DEFERRED, 'label' => Mage::helper('nexi_xpaybuild')->__('Differita')),
        );
    }
}
