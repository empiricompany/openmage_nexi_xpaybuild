<?php
class Nexi_XPayBuild_XpayController extends Nexi_XPayBuild_Controller_Payment_Abstract
{
    /**
     * Build XPay-specific payment data for the checkout iframe.
     *
     * Computes alias, MAC key, importo, divisa, codTrans, timeStamp, mac,
     * SDK/environment config, and optionally saved-card contracts for
     * oneClick flows.
     *
     * XPay constraints:
     * - Every codTrans must be unique for the entire life of the transaction —
     *   reuse (even of abandoned transactions) returns error [9] "Codice duplicato".
     * - The timestamp must be fresh on every call — a stale value causes error [5].
     * - Formula: quoteId + Unix-seconds → guarantees uniqueness; substr to 30 = XPay limit.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     * @throws Exception
     */
    protected function _buildPaymentData(Mage_Sales_Model_Quote $quote)
    {
        $helper = $this->_getHelper();

        $alias  = $helper->getXpayAlias();
        $macKey = $helper->getXpayMacKey();
        if (!$alias || !$macKey) {
            throw new Exception('XPay not configured');
        }

        $importo = $helper->formatAmountToMinorUnit($quote->getGrandTotal(), $quote->getQuoteCurrencyCode());
        $divisa  = $helper->getCurrencyNumericCode($quote->getQuoteCurrencyCode());

        $codTrans  = substr($quote->getId() . '-' . time(), 0, 30);
        $timeStamp = (string)(time() * 1000);

        $helper->log('XpayController::_buildPaymentData codTrans=' . $codTrans);

        $mac = Mage::helper('nexi_xpaybuild/mac')->calculateInitMac($codTrans, $divisa, $importo, $macKey);

        $response = array(
            'alias'         => $alias,
            'importo'       => $importo,
            'codTrans'      => $codTrans,
            'divisa'        => $divisa,
            'timeStamp'     => $timeStamp,
            'mac'           => $mac,
            'sdkUrl'        => $helper->getXpaySdkUrl(),
            'environment'   => $helper->getXpayEnvironment(),
            'language'      => 'ITA',
            'cardFormStyle' => $helper->getCardFormStyle(),
            'saveNonceUrl'  => (string)Mage::getUrl('nexixpaybuild/xpay/saveXpayNonce'),
        );

        $customerSession = Mage::getSingleton('customer/session');
        if ($customerSession->isLoggedIn() && $helper->getOneclickEnabled()) {
            $customerId      = (int)$customerSession->getCustomerId();
            $savedCardHelper = Mage::helper('nexi_xpaybuild/savedCard');
            $savedCards      = $savedCardHelper->getActiveCards($customerId, 'XPAY');
            $contractsData   = array();
            $idx = 0;
            foreach ($savedCards as $card) {
                $codTransCard = substr($card->getId() . '-' . time() . '-' . $idx, 0, 30);
                $idx++;
                $tsCard  = (string)(time() * 1000);
                $macCard = Mage::helper('nexi_xpaybuild/mac')->calculateInitMac(
                    $codTransCard, $divisa, $importo, $macKey
                );
                $contractsData[] = array(
                    'cardId'       => $card->getId(),
                    'maskedPan'    => $card->getMaskedPan(),
                    'brand'        => $card->getBrand(),
                    'expiryMonth'  => $card->getExpiryMonth(),
                    'expiryYear'   => $card->getExpiryYear(),
                    'gatewayToken' => $card->getGatewayToken(),
                    'codTrans'     => $codTransCard,
                    'timeStamp'    => $tsCard,
                    'mac'          => $macCard,
                );
            }
            $response['savedCards']      = $contractsData;
            $response['oneClickEnabled'] = !empty($contractsData);
            $savedCardId = (int)$this->getRequest()->getPost('saved_card_id', 0);
            if ($savedCardId > 0) {
                foreach ($contractsData as $contract) {
                    if ((int)$contract['cardId'] === $savedCardId) {
                        $response['savedCardToken'] = $contract['gatewayToken'];
                        break;
                    }
                }
            }
        }

        return $response;
    }

    /**
     * AJAX: persist XPay nonce and codTrans in the checkout session
     * so that the order placement flow can use them for MAC verification.
     *
     * @return void
     */
    public function saveXpayNonceAction()
    {
        if (!$this->_checkAjax()) return;
        try {
            $nonce    = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$this->getRequest()->getPost('xpay_nonce', ''));
            $codTrans = preg_replace('/[^a-zA-Z0-9\-]/', '', (string)$this->getRequest()->getPost('xpay_cod_trans', ''));
            if (strlen($nonce) > 256 || strlen($codTrans) > 30) {
                throw new Exception('Invalid nonce or codTrans format');
            }

            $savedCardToken = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$this->getRequest()->getPost('saved_card_token', ''));

            $session = $this->_getCheckoutSession();
            $session->setNexiXpayNonce($nonce);
            $session->setNexiXpayCodTrans($codTrans);
            $session->setNexiXpaySavedCardToken($savedCardToken);

            $this->_getHelper()->log('XpayController::saveXpayNonceAction nonce saved to session');

            $this->_sendJson(array('success' => true));
        } catch (Exception $e) {
            $this->_sendJsonError($e, 'XpayController::saveXpayNonceAction');
        }
    }
}
