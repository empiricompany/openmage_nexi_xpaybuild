<?php
abstract class Nexi_XPayBuild_Controller_Payment_Abstract extends Mage_Core_Controller_Front_Action
{
    /** @var Nexi_XPayBuild_Helper_Data|null */
    protected $_helper = null;

    /** @var Mage_Sales_Model_Quote|null */
    protected $_quote = null;

    /**
     * @return Nexi_XPayBuild_Helper_Data
     */
    protected function _getHelper()
    {
        if ($this->_helper === null) {
            $this->_helper = Mage::helper('nexi_xpaybuild');
        }
        return $this->_helper;
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Throws if the session has no valid quote.
     *
     * @return Mage_Sales_Model_Quote
     * @throws Exception
     */
    protected function _getQuote()
    {
        if ($this->_quote === null) {
            $quote = $this->_getCheckoutSession()->getQuote();
            if (!$quote || !$quote->getId()) {
                throw new Exception('No active quote found');
            }
            $this->_quote = $quote;
        }
        return $this->_quote;
    }

    /**
     * @param array $data
     * @return void
     */
    protected function _sendJson(array $data)
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));
    }

    /**
     * Log the exception as ERR, set HTTP 500, and write a JSON error body.
     *
     * @param Exception $e
     * @param string    $ctx  Human-readable context label prepended to the log entry
     * @return void
     */
    protected function _sendJsonError(Exception $e, $ctx = '')
    {
        $label = $ctx ? $ctx . ' error: ' : 'error: ';
        $this->_getHelper()->log($label . $e->getMessage(), Zend_Log::ERR);
        $this->getResponse()->setHttpResponseCode(500);
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
            'error'   => true,
            'message' => $e->getMessage(),
        )));
    }

    /**
     * Guard: verify that the current request carries X-Requested-With: XMLHttpRequest.
     * Sets HTTP 403 and returns false if the check fails.
     *
     * @return bool
     */
    protected function _checkAjax()
    {
        if (!$this->getRequest()->isAjax()) {
            $this->getResponse()->setHttpResponseCode(403);
            return false;
        }
        if (!$this->_validateFormKey()) {
            $this->getResponse()->setHttpResponseCode(403);
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
                'error'   => true,
                'message' => 'Invalid form key (CSRF check failed).',
            )));
            return false;
        }
        return true;
    }

    /**
     * Build gateway-specific payment data for the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     */
    abstract protected function _buildPaymentData(Mage_Sales_Model_Quote $quote);

    /**
     * AJAX: return JSON payment data for the checkout hosted-fields iframe.
     *
     * @return void
     */
    public function getPaymentDataAction()
    {
        if (!$this->_checkAjax()) return;
        try {
            $this->_getHelper()->log(get_class($this) . '::getPaymentDataAction');
            $this->_sendJson($this->_buildPaymentData($this->_getQuote()));
        } catch (Exception $e) {
            $this->_sendJsonError($e, 'getPaymentDataAction');
        }
    }
}
