<?php
class Nexi_XPayBuild_Model_Resource_SavedCard extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('nexi_xpaybuild/saved_card', 'id');
    }
}
