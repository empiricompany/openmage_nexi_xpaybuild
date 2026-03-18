<?php
class Nexi_XPayBuild_AccountController extends Mage_Core_Controller_Front_Action
{
    /**
     * Verify that the customer is authenticated.
     * If not, redirect to the login page.
     *
     * @return bool
     */
    private function _checkAuthentication()
    {
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return false;
        }
        return true;
    }

    /**
     * List customer's saved cards.
     */
    public function indexAction()
    {
        if (!$this->_checkAuthentication()) {
            return;
        }

        $this->loadLayout();

        $root = $this->getLayout()->getBlock('root');
        if ($root) {
            $root->setTitle($this->__('My Payment Cards'));
        }

        $this->renderLayout();
    }

    /**
     * Delete a customer's saved card.
     */
    public function deleteAction()
    {
        if (!$this->_checkAuthentication()) {
            return;
        }

        // Block GET requests (stale form_key in URL)
        if (!$this->getRequest()->isPost()) {
            $this->_redirect('nexixpaybuild/account/index');
            return;
        }

        // CSRF check: POST with valid form_key only
        if (!$this->_validateFormKey()) {
            Mage::getSingleton('core/session')->addError(
                $this->__('Richiesta non valida.')
            );
            $this->_redirect('nexixpaybuild/account/index');
            return;
        }

        $cardId     = (int)$this->getRequest()->getPost('id');

        // Guard against invalid card ID
        if ($cardId <= 0) {
            Mage::getSingleton('core/session')->addError($this->__('Invalid request.'));
            $this->_redirect('nexixpaybuild/account/index');
            return;
        }

        $customerId = (int)Mage::getSingleton('customer/session')->getCustomerId();

        try {
            Mage::helper('nexi_xpaybuild/savedCard')->deleteCard($cardId, $customerId);

            Mage::getSingleton('core/session')->addSuccess(
                $this->__('La carta è stata rimossa con successo.')
            );
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('core/session')->addError(
                $this->__('Impossibile rimuovere la carta. Riprova.')
            );
        }

        $this->_redirect('nexixpaybuild/account/index');
    }
}
