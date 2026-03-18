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
                'value' => 'SPLIT_CARD',
                'label' => $helper->__('Split (3 separate fields: PAN, Expiry, CVV)'),
            ),
            array(
                'value' => 'CARD',
                'label' => $helper->__('Unified (single combined form)'),
            ),
        );
    }
}
