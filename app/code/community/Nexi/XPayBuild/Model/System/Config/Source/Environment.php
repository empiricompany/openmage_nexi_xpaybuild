<?php
class Nexi_XPayBuild_Model_System_Config_Source_Environment
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'test', 'label' => Mage::helper('nexi_xpaybuild')->__('Test')),
            array('value' => 'production', 'label' => Mage::helper('nexi_xpaybuild')->__('Produzione')),
        );
    }
}
