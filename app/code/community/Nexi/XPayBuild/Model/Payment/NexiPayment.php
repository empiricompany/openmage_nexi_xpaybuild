<?php
class Nexi_XPayBuild_Model_Payment_NexiPayment extends Mage_Payment_Model_Method_Abstract
{
    protected $_code          = 'nexi_xpaybuild';
    protected $_formBlockType = 'nexi_xpaybuild/form_build';
    protected $_infoBlockType = 'nexi_xpaybuild/info';

    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;

    /**
     * Delegates to parent, then verifies XPay credentials (alias + MAC key).
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        if (!parent::isAvailable($quote)) {
            return false;
        }

        /** @var Nexi_XPayBuild_Helper_Data $helper */
        $helper = Mage::helper('nexi_xpaybuild');

        if (!$helper->getXpayAlias() || !$helper->getXpayMacKey()) {
            return false;
        }

        // In TEST mode, only show to IPs allowed in dev/restrict/allow_ips
        if ($helper->getXpayEnvironment() === 'test') {
            if (!Mage::helper('core')->isDevAllowed()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Stores save-card flags and XPay session tokens from the frontend form.
     *
     * @param Varien_Object|array $data
     * @return $this
     */
    public function assignData($data)
    {
        parent::assignData($data);

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();

        $info->setAdditionalInformation('nexi_gateway', 'XPAY');

        // Saved-card selection
        $savedCardIdRaw = $data->getData('saved_card_id');
        $info->setAdditionalInformation(
            'saved_card_id',
            $savedCardIdRaw !== null ? (int)$savedCardIdRaw : 0
        );

        $saveCardRaw = $data->getData('save_card');
        $info->setAdditionalInformation(
            'save_card',
            $saveCardRaw !== null ? (bool)$saveCardRaw : false
        );

        // XPay-specific fields
        $xpayNonce = $data->getData('xpay_nonce');
        $info->setAdditionalInformation(
            'xpay_nonce',
            $xpayNonce !== null ? (string)$xpayNonce : ''
        );

        $xpayCodTrans = $data->getData('xpay_cod_trans');
        $info->setAdditionalInformation(
            'xpay_cod_trans',
            $xpayCodTrans !== null ? (string)$xpayCodTrans : ''
        );

        $xpayTimestamp = $data->getData('xpay_timestamp');
        $info->setAdditionalInformation(
            'xpay_timestamp',
            $xpayTimestamp !== null ? (string)$xpayTimestamp : ''
        );

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float         $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $savedCardId = (int)$payment->getAdditionalInformation('saved_card_id');
        $saveCard    = (bool)$payment->getAdditionalInformation('save_card');

        if ($savedCardId > 0) {
            return $this->_authorizeXpayOneClick($payment, $amount, $savedCardId);
        }
        return $this->_authorizeXpay($payment, $amount, $saveCard);
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float         $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        return $this->_captureXpay($payment, $amount);
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float         $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        return $this->_refundXpay($payment, $amount);
    }

    /**
     * Void is not supported by XPay — use refund instead.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function void(Varien_Object $payment)
    {
        /** @var Nexi_XPayBuild_Helper_Data $helper */
        $helper = Mage::helper('nexi_xpaybuild');
        Mage::throwException(
            $helper->__('Void not supported for XPay gateway. Use refund instead.')
        );
    }

    /**
     * Reads from the unified config group; falls back to parent (DB value).
     *
     * @return string
     */
    public function getTitle()
    {
        $title = Mage::helper('nexi_xpaybuild')->getConfig('title');
        return $title ? $title : parent::getTitle();
    }

    /**
     * Authorize a standard XPay payment using a one-time nonce.
     *
     * Flow:
     * 1. Read xpay_nonce and xpay_cod_trans from additionalInfo.
     * 2. Fallback: read from checkout session (multi-step checkout scenario).
     * 3. Call XpayClient::pagaNonce().
     * 4. On esito OK / PEN: set transaction state.
     * 5. On esito OK + $saveCard: call _saveXpayCard().
     * 6. On any other esito: throw exception.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float         $amount
     * @param bool          $saveCard
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _authorizeXpay(Varien_Object $payment, $amount, $saveCard)
    {
        /** @var Nexi_XPayBuild_Helper_Data $helper */
        $helper = Mage::helper('nexi_xpaybuild');

        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        $xpayNonce = $payment->getAdditionalInformation('xpay_nonce');
        $codTrans  = $payment->getAdditionalInformation('xpay_cod_trans');

        // Fallback: read nonce from checkout session (for multi-step checkouts
        // where review.save() does not re-send payment form data)
        if (empty($xpayNonce)) {
            $session   = Mage::getSingleton('checkout/session');
            $xpayNonce = $session->getNexiXpayNonce();
            if ($xpayNonce) {
                $payment->setAdditionalInformation('xpay_nonce', $xpayNonce);
                $helper->log('NexiPayment::_authorizeXpay() nonce read from session fallback');
            }
            if (empty($codTrans)) {
                $codTrans = $session->getNexiXpayCodTrans();
                if ($codTrans) {
                    $payment->setAdditionalInformation('xpay_cod_trans', $codTrans);
                }
            }
        }
        
        $session = Mage::getSingleton('checkout/session');
        $session->unsNexiXpayNonce();
        $session->unsNexiXpayCodTrans();

        if (empty($xpayNonce)) {
            Mage::throwException(
                $helper->__('XPay nonce is missing. Please retry the payment.')
            );
        }

        if (empty($codTrans)) {
            $codTrans = $helper->generateTransactionId($order);
        }

        $currencyCode   = $order->getOrderCurrencyCode();
        $importo        = $helper->formatAmountToMinorUnit($amount, $currencyCode);
        $divisa         = $helper->getCurrencyNumericCode($currencyCode);
        $accountingType = $helper->getAccountingType();

        /** @var Mage_Sales_Model_Order_Address $billingAddress */
        $billingAddress = $order->getBillingAddress();

        $helper->log(
            'NexiPayment::_authorizeXpay() codTrans=' . $codTrans .
            ' importo=' . $importo .
            ' divisa=' . $divisa .
            ' accountingType=' . $accountingType
        );

        /** @var Nexi_XPayBuild_Model_Api_XpayClient $client */
        $client = Mage::getModel('nexi_xpaybuild/api_xpayClient');

        $numeroContratto = null;
        try {
            if ($saveCard && $order->getCustomerId()) {
                $numeroContratto = Mage::helper('nexi_xpaybuild/savedCard')
                    ->generateXpayContractNumber($order->getCustomerId());
                $response = $client->pagaNonceCreazioneContratto(
                    $codTrans,
                    $importo,
                    $divisa,
                    $xpayNonce,
                    $numeroContratto,
                    $accountingType,
                    $billingAddress->getFirstname(),
                    $billingAddress->getLastname(),
                    $order->getCustomerEmail(),
                    $order->getIncrementId()
                );
            } else {
                $response = $client->pagaNonce(
                    $codTrans,
                    $importo,
                    $divisa,
                    $xpayNonce,
                    $accountingType,
                    $billingAddress->getFirstname(),
                    $billingAddress->getLastname(),
                    $order->getCustomerEmail(),
                    $order->getIncrementId()
                );
            }
        } catch (Mage_Core_Exception $e) {
            $helper->log('XpayClient error: ' . $e->getMessage(), Zend_Log::ERR);
            throw new Mage_Payment_Model_Info_Exception(
                $helper->__('Pagamento non riuscito. Si prega di riprovare.')
            );
        }

        $esito = isset($response['esito']) ? $response['esito'] : '';

        $helper->log(
            'NexiPayment::_authorizeXpay() esito=' . $esito .
            ' response=' . json_encode($response)
        );

        if ($esito === 'OK') {
            $payment->setTransactionId($codTrans);

            // Persist relevant response fields + RAW_DETAILS for the admin "Transaction Details" tab
             $rawDetails = $this->_saveXpayResponseFields(
                 $payment, $response, array('codAut', 'codiceAutorizzazione', 'brand', 'pan', 'scadenza', 'scadenzaPan')
             );

            if ($accountingType === Nexi_XPayBuild_Model_Api_XpayClient::XPAY_TCONTAB_IMMEDIATE) {
                $payment->setIsTransactionClosed(true);
                $payment->setIsTransactionPending(false);
                Mage::helper('nexi_xpaybuild')->createInvoiceForOrder($order, $codTrans);
            } else {
                $payment->setIsTransactionClosed(false);
            }

            if ($saveCard) {
                $this->_saveXpayCard($payment, $response, $numeroContratto);
            }
        } elseif ($esito === 'PEN') {
            // Pending: awaiting S2S notification
            $payment->setTransactionId($codTrans);
            $payment->setIsTransactionPending(true);
            $payment->setIsTransactionClosed(false);
        } else {
            $codiceEsito = isset($response['errore']['codice']) ? (int)$response['errore']['codice'] : null;
            $nexiMsg     = isset($response['errore']['messaggio']) ? $response['errore']['messaggio'] : '';
            $helper->log(
                'NexiPayment::_authorizeXpay() DECLINED codiceEsito=' . $codiceEsito .
                ' nexiMsg=' . $nexiMsg,
                Zend_Log::WARN
            );
            throw new Mage_Payment_Model_Info_Exception(
                $helper->__('Pagamento non riuscito. Verifica i dati della carta o usa un altro metodo di pagamento.')
            );
        }

        return $this;
    }

    /**
     * Authorize a recurring (OneClick) XPay payment using a saved-card nonce.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float         $amount
     * @param int           $savedCardId  Internal saved-card record ID (for tracking)
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _authorizeXpayOneClick(Varien_Object $payment, $amount, $savedCardId)
    {
        /** @var Nexi_XPayBuild_Helper_Data $helper */
        $helper = Mage::helper('nexi_xpaybuild');

        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        $xpayNonce = $payment->getAdditionalInformation('xpay_nonce');
        $codTrans  = $payment->getAdditionalInformation('xpay_cod_trans');

        // Fallback: read from checkout session (multi-step checkout scenario)
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');

        if (empty($xpayNonce)) {
            $xpayNonce = $session->getNexiXpayNonce();
            if ($xpayNonce) {
                $payment->setAdditionalInformation('xpay_nonce', $xpayNonce);
                $helper->log('NexiPayment::_authorizeXpayOneClick() nonce read from session fallback');
            }
            if (empty($codTrans)) {
                $codTrans = $session->getNexiXpayCodTrans();
                if ($codTrans) {
                    $payment->setAdditionalInformation('xpay_cod_trans', $codTrans);
                }
            }
        }

        $savedCardToken = $session->getNexiXpaySavedCardToken();

        $session->unsNexiXpayNonce();
        $session->unsNexiXpayCodTrans();
        $session->unsNexiXpaySavedCardToken();

        if (empty($xpayNonce)) {
            Mage::throwException(
                $helper->__('XPay nonce is missing. Please retry the payment.')
            );
        }

        if (empty($savedCardToken)) {
            Mage::throwException(
                $helper->__('No saved card selected. Please retry the payment.')
            );
        }

        if (empty($codTrans)) {
            $codTrans = $helper->generateTransactionId($order);
        }

        $currencyCode   = $order->getOrderCurrencyCode();
        $importo        = $helper->formatAmountToMinorUnit($amount, $currencyCode);
        $divisa         = $helper->getCurrencyNumericCode($currencyCode);
        $accountingType = $helper->getAccountingType();

        $helper->log(
            'NexiPayment::_authorizeXpayOneClick() codTrans=' . $codTrans .
            ' importo=' . $importo .
            ' divisa=' . $divisa .
            ' accountingType=' . $accountingType .
            ' savedCardId=' . $savedCardId .
            ' savedCardToken=...' . substr($savedCardToken, -4)
        );

        /** @var Nexi_XPayBuild_Model_Api_XpayClient $client */
        $client = Mage::getModel('nexi_xpaybuild/api_xpayClient');

        $billingAddress = $order->getBillingAddress();
        try {
            $response = $client->pagamentoRicorrente3DS(
                $codTrans,
                $importo,
                $divisa,
                $xpayNonce,
                $accountingType,
                $billingAddress ? $billingAddress->getFirstname() : null,
                $billingAddress ? $billingAddress->getLastname() : null,
                $order->getCustomerEmail(),
                $order->getIncrementId()
            );
        } catch (Mage_Core_Exception $e) {
            $helper->log('XpayClient error (OneClick): ' . $e->getMessage(), Zend_Log::ERR);
            throw new Mage_Payment_Model_Info_Exception(
                $helper->__('Pagamento non riuscito. Si prega di riprovare.')
            );
        }

        $esito = isset($response['esito']) ? $response['esito'] : '';

        $helper->log(
            'NexiPayment::_authorizeXpayOneClick() esito=' . $esito .
            ' response=' . json_encode($response)
        );

        if ($esito === 'OK') {
            $payment->setTransactionId($codTrans);

            $rawDetails = $this->_saveXpayResponseFields(
                $payment, $response, array('codiceAutorizzazione', 'codAut', 'brand', 'pan', 'scadenza', 'scadenzaPan')
            );

            // pagamentoRicorrente3DS does not return pan/scadenza (and sometimes not even brand).
            // We recover them from the saved card in DB via $savedCardId.
            /** @var Nexi_XPayBuild_Model_SavedCard $savedCard */
            $savedCard = Mage::getModel('nexi_xpaybuild/savedCard')->load($savedCardId);
            if ($savedCard && $savedCard->getId()) {
                if (empty($rawDetails['brand']) && $savedCard->getBrand()) {
                    $payment->setAdditionalInformation('xpay_brand', $savedCard->getBrand());
                    $rawDetails['brand'] = $savedCard->getBrand();
                }
                if (empty($rawDetails['pan']) && $savedCard->getMaskedPan()) {
                    $payment->setAdditionalInformation('xpay_pan', $savedCard->getMaskedPan());
                    $rawDetails['pan'] = $savedCard->getMaskedPan();
                }
                if (empty($rawDetails['scadenza']) && $savedCard->getExpiryYear()) {
                    $scadenza = sprintf('%04d%02d', $savedCard->getExpiryYear(), (int)$savedCard->getExpiryMonth());
                    $payment->setAdditionalInformation('xpay_scadenza', $scadenza);
                    $rawDetails['scadenza'] = $scadenza;
                }

                $payment->setTransactionAdditionalInfo(
                    Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                    $rawDetails
                );
            }

            if ($accountingType === Nexi_XPayBuild_Model_Api_XpayClient::XPAY_TCONTAB_IMMEDIATE) {
                $payment->setIsTransactionClosed(true);
                $payment->setIsTransactionPending(false);
                Mage::helper('nexi_xpaybuild')->createInvoiceForOrder($order, $codTrans);
            } else {
                $payment->setIsTransactionClosed(false);
            }
        } else {
            $codiceEsito = isset($response['errore']['codice']) ? (int)$response['errore']['codice'] : null;
            $nexiMsg     = isset($response['errore']['messaggio']) ? $response['errore']['messaggio'] : '';
            $helper->log(
                'NexiPayment::_authorizeXpayOneClick() DECLINED codiceEsito=' . $codiceEsito .
                ' nexiMsg=' . $nexiMsg,
                Zend_Log::WARN
            );
            throw new Mage_Payment_Model_Info_Exception(
                $helper->__('Pagamento non riuscito. Verifica i dati della carta o usa un altro metodo di pagamento.')
            );
        }

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float         $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _captureXpay(Varien_Object $payment, $amount)
    {
        /** @var Nexi_XPayBuild_Helper_Data $helper */
        $helper = Mage::helper('nexi_xpaybuild');

        /** @var Mage_Sales_Model_Order $order */
        $order    = $payment->getOrder();
        $codTrans = $payment->getAdditionalInformation('xpay_cod_trans');

        if (empty($codTrans)) {
            $codTrans = $payment->getLastTransId();
        }

        $currencyCode = $order->getOrderCurrencyCode();
        $importo      = $helper->formatAmountToMinorUnit($amount, $currencyCode);
        $divisa       = $helper->getCurrencyNumericCode($currencyCode);

        $helper->log(
            'NexiPayment::capture() [XPAY] codTrans=' . $codTrans .
            ' importo=' . $importo .
            ' divisa=' . $divisa
        );

        /** @var Nexi_XPayBuild_Model_Api_XpayClient $client */
        $client   = Mage::getModel('nexi_xpaybuild/api_xpayClient');
        $response = $client->capture($codTrans, $importo, $divisa);

        $esito = isset($response['esito']) ? $response['esito'] : '';

        $helper->log(
            'NexiPayment::capture() [XPAY] esito=' . $esito .
            ' response=' . json_encode($response)
        );

        if ($esito !== 'OK') {
            $errorMsg = isset($response['errore']['messaggio'])
                ? $response['errore']['messaggio']
                : Mage::helper('nexi_xpaybuild')->__('Capture failed (XPay). Esito: %s', $esito);
            Mage::throwException($errorMsg);
        }

        $this->_saveXpayResponseFields(
            $payment, $response, array('codiceAutorizzazione', 'codAut', 'brand', 'pan', 'scadenza', 'scadenzaPan')
        );

        $payment->setTransactionId($codTrans . '-capture');
        $payment->setIsTransactionClosed(true);

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float         $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _refundXpay(Varien_Object $payment, $amount)
    {
        /** @var Nexi_XPayBuild_Helper_Data $helper */
        $helper = Mage::helper('nexi_xpaybuild');

        /** @var Mage_Sales_Model_Order $order */
        $order    = $payment->getOrder();
        $codTrans = $payment->getAdditionalInformation('xpay_cod_trans');

        if (empty($codTrans)) {
            $codTrans = $payment->getLastTransId();
        }

        $currencyCode = $order->getOrderCurrencyCode();
        $importo      = $helper->formatAmountToMinorUnit($amount, $currencyCode);
        $divisa       = $helper->getCurrencyNumericCode($currencyCode);

        $helper->log(
            'NexiPayment::refund() [XPAY] codTrans=' . $codTrans .
            ' importo=' . $importo .
            ' divisa=' . $divisa
        );

        /** @var Nexi_XPayBuild_Model_Api_XpayClient $client */
        $client   = Mage::getModel('nexi_xpaybuild/api_xpayClient');
        $response = $client->refund($codTrans, $importo, $divisa);

        $esito = isset($response['esito']) ? $response['esito'] : '';

        $helper->log(
            'NexiPayment::refund() [XPAY] esito=' . $esito .
            ' response=' . json_encode($response)
        );

        if ($esito !== 'OK') {
            $errorMsg = isset($response['errore']['messaggio'])
                ? $response['errore']['messaggio']
                : Mage::helper('nexi_xpaybuild')->__('Refund failed (XPay). Esito: %s', $esito);
            Mage::throwException($errorMsg);
        }

        $this->_saveXpayResponseFields(
            $payment, $response, array('codiceAutorizzazione', 'codAut', 'brand', 'pan', 'scadenza', 'scadenzaPan')
        );
        
        $payment->setTransactionId($codTrans . '-refund');
        $payment->setIsTransactionClosed(true);

        return $this;
    }

    /**
     * Persist a new XPay card contract for the current customer.
     *
     * Called after a successful pagaNonce() authorize when the customer opted in
     * to save their card. Extracts card metadata from the XPay gateway response
     * (brand, masked PAN, expiry date) and delegates to SavedCard helper.
     *
     * Non-critical: a failure here logs a warning but does NOT abort the order.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param array         $response  Raw XPay pagaNonce response array
     * @param string|null   $numeroContratto
     * @return void
     */
    protected function _saveXpayCard(Varien_Object $payment, array $response, $numeroContratto = null)
    {
        $order      = $payment->getOrder();
        $customerId = (int)$order->getCustomerId();

        if (!$customerId) {
            Mage::helper('nexi_xpaybuild')->log(
                'NexiPayment::_saveXpayCard() — no customerId on order; skipping card save.',
                Zend_Log::WARN
            );
            return;
        }

        if (!empty($numeroContratto)) {
            $gatewayToken = (string)$numeroContratto;
        } else {
            $gatewayToken = isset($response['codiceAutorizzazione'])
                ? (string)$response['codiceAutorizzazione']
                : (isset($response['codAut']) ? (string)$response['codAut'] : '');
        }

        if (empty($gatewayToken)) {
            Mage::helper('nexi_xpaybuild')->log(
                'NexiPayment::_saveXpayCard() — no gatewayToken/codiceAutorizzazione in response; skipping card save.',
                Zend_Log::WARN
            );
            return;
        }

        $maskedPan = isset($response['pan'])   ? (string)$response['pan']   : null;
        $brand     = isset($response['brand']) ? (string)$response['brand'] : null;

        $expiryMonth = null;
        $expiryYear  = null;

        $expiryRaw = isset($response['scadenzaPan'])
            ? (string)$response['scadenzaPan']
            : (isset($response['scadenza']) ? (string)$response['scadenza'] : '');

        if (!empty($expiryRaw)) {
            if (strpos($expiryRaw, '/') !== false) {
                $parts = explode('/', $expiryRaw);
                if (count($parts) === 2) {
                    $expiryMonth = (int)$parts[0];
                    $expiryYear  = strlen($parts[1]) === 4
                        ? (int)$parts[1]
                        : 2000 + (int)$parts[1];
                }
            } elseif (strlen($expiryRaw) === 6 && ctype_digit($expiryRaw)) {
                $expiryYear  = (int)substr($expiryRaw, 0, 4);
                $expiryMonth = (int)substr($expiryRaw, 4, 2);
            } elseif (strlen($expiryRaw) === 4 && ctype_digit($expiryRaw)) {
                $expiryMonth = (int)substr($expiryRaw, 0, 2);
                $expiryYear  = 2000 + (int)substr($expiryRaw, 2, 2);
            }
        }

        Mage::helper('nexi_xpaybuild')->log(
            'NexiPayment::_saveXpayCard() customerId=' . $customerId .
            ' gatewayToken=...' . substr($gatewayToken, -4)
        );

        try {
            /** @var Nexi_XPayBuild_Helper_SavedCard $savedCardHelper */
            $savedCardHelper = Mage::helper('nexi_xpaybuild/savedCard');
            $savedCardHelper->saveCard(
                $customerId,
                'XPAY',
                $gatewayToken,
                $maskedPan,
                $brand,
                $expiryMonth,
                $expiryYear
            );

            Mage::helper('nexi_xpaybuild')->log(
                'NexiPayment::_saveXpayCard() — card saved for customer ' . $customerId
            );
        } catch (Exception $e) {
            Mage::helper('nexi_xpaybuild')->log(
                'NexiPayment::_saveXpayCard() — FAILED for customer ' . $customerId .
                ': ' . $e->getMessage(),
                Zend_Log::WARN
            );
        }
    }

    /**
     * Save XPay response fields to additionalInformation and RAW_DETAILS.
     *
     * $specialFields are saved to additionalInformation (xpay_ prefix) AND rawDetails.
     * All other non-esito fields are saved to rawDetails only.
     * dettagliCarta arrays are also flattened as dettagliCarta_* keys.
     *
     * @param  Mage_Sales_Model_Order_Payment $payment
     * @param  array                          $response
     * @param  array                          $specialFields
     * @return array
     */
    protected function _saveXpayResponseFields(Varien_Object $payment, $response, $specialFields = array())
    {
        $rawDetails = array();

        foreach ($specialFields as $field) {
            if (isset($response[$field])) {
                $payment->setAdditionalInformation('xpay_' . $field, $response[$field]);
                $rawDetails[$field] = $response[$field];
            }
        }

        foreach ($response as $key => $value) {
            if (!isset($rawDetails[$key]) && $key !== 'esito') {
                if (is_scalar($value) || is_null($value)) {
                    $rawDetails[$key] = $value;
                } elseif (is_array($value)) {
                    if ($key === 'dettagliCarta') {
                        foreach ($value as $subKey => $subValue) {
                            $rawDetails['dettagliCarta_' . $subKey] = $subValue;
                        }
                        $rawDetails[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    } else {
                        $rawDetails[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
            }
        }

        $payment->setTransactionAdditionalInfo(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
            $rawDetails
        );

        return $rawDetails;
    }
}
