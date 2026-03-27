<?php
class Nexi_XPayBuild_Model_System_Config_Source_AccountingType
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'C', 'label' => Mage::helper('nexi_xpaybuild')->__('Immediata')),
            array('value' => 'D', 'label' => Mage::helper('nexi_xpaybuild')->__('Differita')),
        );
    }
}
