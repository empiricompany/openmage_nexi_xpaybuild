<?php
class Nexi_XPayBuild_Block_Customer_Cards extends Mage_Core_Block_Template
{
    /**
     * @return Nexi_XPayBuild_Model_SavedCard[]
     */
    public function getSavedCards()
    {
        if (!$this->hasData('saved_cards')) {
            $customerId = Mage::getSingleton('customer/session')->getCustomerId();
            $cards = $customerId
                ? Mage::helper('nexi_xpaybuild/savedCard')->getActiveCards((int)$customerId)
                : array();
            $this->setData('saved_cards', $cards);
        }
        return $this->getData('saved_cards');
    }

    /**
     * @return string
     */
    public function getDeleteUrl()
    {
        return $this->getUrl('nexixpaybuild/account/delete');
    }

    /**
     * @return string
     */
    public function getFormKey()
    {
        return Mage::getSingleton('core/session')->getFormKey();
    }

    /**
     * @return string
     */
    public function getPageTitle()
    {
        return $this->__('Le mie carte di pagamento');
    }
}
