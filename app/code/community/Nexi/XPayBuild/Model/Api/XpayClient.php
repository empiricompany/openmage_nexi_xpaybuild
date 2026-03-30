<?php
class Nexi_XPayBuild_Model_Api_XpayClient
{
    const XPAY_BASE_URL_PRODUCTION = 'https://ecommerce.nexi.it/';
    const XPAY_BASE_URL_TEST = 'https://int-ecommerce.nexi.it/';

    const XPAY_URI_PAGA_NONCE = 'ecomm/api/hostedPayments/pagaNonce';
    const XPAY_URI_PAGA_NONCE_CREA_CONTRATTO = 'ecomm/api/hostedPayments/pagaNonceCreazioneContratto';
    const XPAY_URI_RICORRENTE_3DS = 'ecomm/api/recurring/pagamentoRicorrente3DS';
    const XPAY_URI_ACCOUNT = 'ecomm/api/bo/contabilizza';
    const XPAY_URI_REFUND = 'ecomm/api/bo/storna';
    const XPAY_URI_ORDER_DETAIL = 'ecomm/api/bo/situazioneOrdine';
    const XPAY_URI_PROFILE_INFO = 'ecomm/api/profileInfo';

    const XPAY_TCONTAB_IMMEDIATE = 'C';
    const XPAY_TCONTAB_DEFERRED = 'D';

    /**
     * @var Nexi_XPayBuild_Helper_Data
     */
    protected $_helper;

    public function __construct()
    {
        $this->_helper = Mage::helper('nexi_xpaybuild');
    }

    /**
     * Execute accounting (capture) API call
     *
     * @see https://ecommerce.nexi.it/specifiche-tecniche/apibackoffice/incasso.html
     * @param string     $codiceTransazione
     * @param int        $importo            Amount in minor units
     * @param int|string $divisa             ISO 4217 numeric currency code
     * @return array
     * @throws Mage_Core_Exception
     */
    public function capture($codiceTransazione, $importo, $divisa)
    {
        $helper  = $this->_helper;
        $alias   = $helper->getXpayAlias();
        $macKey  = $helper->getXpayMacKey();

        $timeStamp = (string)(time() * 1000);

        $payload = array(
            'apiKey'            => $alias,
            'codiceTransazione' => $codiceTransazione,
            'importo'           => (int)$importo,
            'divisa'            => (int)$divisa,
            'timeStamp'         => $timeStamp,
            'mac'               => Mage::helper('nexi_xpaybuild/mac')->calculateAccountingMac(
                $alias,
                $codiceTransazione,
                $importo,
                $divisa,
                $timeStamp,
                $macKey
            ),
        );

        return $this->_doPost(self::XPAY_URI_ACCOUNT, $payload);
    }

    /**
     * Execute refund (storna) API call
     *
     * @see https://ecommerce.nexi.it/specifiche-tecniche/apibackoffice/stornorimborso.html
     * @param string     $codiceTransazione
     * @param int        $importo            Amount in minor units
     * @param int|string $divisa             ISO 4217 numeric currency code
     * @return array
     * @throws Mage_Core_Exception
     */
    public function refund($codiceTransazione, $importo, $divisa)
    {
        $helper  = $this->_helper;
        $alias   = $helper->getXpayAlias();
        $macKey  = $helper->getXpayMacKey();

        $timeStamp = (string)(time() * 1000);

        $payload = array(
            'apiKey'            => $alias,
            'codiceTransazione' => $codiceTransazione,
            'importo'           => (int)$importo,
            'divisa'            => (int)$divisa,
            'timeStamp'         => $timeStamp,
            'mac'               => Mage::helper('nexi_xpaybuild/mac')->calculateAccountingMac(
                $alias,
                $codiceTransazione,
                $importo,
                $divisa,
                $timeStamp,
                $macKey
            ),
        );

        return $this->_doPost(self::XPAY_URI_REFUND, $payload);
    }

    /**
     * Retrieve order detail / payment status
     *
     * @see https://ecommerce.nexi.it/specifiche-tecniche/apibackoffice/interrogazionedettaglioordine.html
     * @param string $codiceTransazione
     * @return array
     * @throws Mage_Core_Exception
     */
    public function orderDetail($codiceTransazione)
    {
        $helper  = $this->_helper;
        $alias   = $helper->getXpayAlias();
        $macKey  = $helper->getXpayMacKey();

        $timeStamp = (string)(time() * 1000);

        $payload = array(
            'apiKey'            => $alias,
            'codiceTransazione' => $codiceTransazione,
            'timeStamp'         => $timeStamp,
            'mac'               => Mage::helper('nexi_xpaybuild/mac')->calculateOrderDetailMac(
                $alias,
                $codiceTransazione,
                $timeStamp,
                $macKey
            ),
        );

        return $this->_doPost(self::XPAY_URI_ORDER_DETAIL, $payload);
    }

    /**
     * Retrieve profile info (available payment methods, logo URL)
     *
     * @see https://ecommerce.nexi.it/specifiche-tecniche/apibackoffice/metodidipagamentoattivi.html
     * @return array
     * @throws Mage_Core_Exception
     */
    public function profileInfo()
    {
        $helper  = $this->_helper;
        $alias   = $helper->getXpayAlias();
        $macKey  = $helper->getXpayMacKey();

        if (method_exists('Mage', 'getOpenMageVersion')) {
            $platform = 'openmage';
            $platformVersion = Mage::getOpenMageVersion();
        } else {
            $platform = 'magento';
            $platformVersion = Mage::getVersion();
        }

        $timeStamp = (string)(time() * 1000);

        $payload = array(
            'apiKey'    => $alias,
            'timeStamp' => $timeStamp,
            'mac'       => Mage::helper('nexi_xpaybuild/mac')->calculateProfileInfoMac(
                $alias,
                $timeStamp,
                $macKey
            ),
            'platform'     => $platform,
            'platformVers' => $platformVersion,
            'pluginVers'   => $helper->getModuleVersion(),
        );

        return $this->_doPost(self::XPAY_URI_PROFILE_INFO, $payload);
    }

    /**
     * Build common payload for nonce-based payment methods
     *
     * @param string      $codiceTransazione
     * @param int         $importo            Amount in minor units (cents)
     * @param int|string  $divisa             ISO 4217 numeric currency code
     * @param string      $xpayNonce
     * @param string      $accountingType     'C' for immediate capture (TCONTAB=C) or 'D' for deferred (TCONTAB=D)
     * @param string|null $customerName
     * @param string|null $customerSurname
     * @param string|null $customerEmail
     * @param string|null $incrementId
     * @param string|null $numeroContratto    Optional contract ID for contract creation
     * @return array Built payload array
     */
    protected function _buildNoncePayload(
        $codiceTransazione,
        $importo,
        $divisa,
        $xpayNonce,
        $accountingType,
        $customerName    = null,
        $customerSurname = null,
        $customerEmail   = null,
        $incrementId     = null,
        $numeroContratto = null
    ) {
        $helper  = $this->_helper;
        $alias   = $helper->getXpayAlias();
        $macKey  = $helper->getXpayMacKey();

        $timeStamp = (string)(time() * 1000);

        $payload = array(
            'apiKey'             => $alias,
            'codiceTransazione'  => $codiceTransazione,
            'importo'            => (int)$importo,
            'divisa'             => (int)$divisa,
            'xpayNonce'          => $xpayNonce,
            'timeStamp'          => $timeStamp,
            'mac'                => Mage::helper('nexi_xpaybuild/mac')->calculateNonceMac(
                $alias,
                $codiceTransazione,
                $importo,
                $divisa,
                $xpayNonce,
                $timeStamp,
                $macKey
            ),
            'parametriAggiuntivi' => array(
                'TCONTAB' => $accountingType,
                'Note2'   => $helper->getPluginSignature(),
            ),
        );

        if ($numeroContratto !== null) {
            $payload['numeroContratto'] = $numeroContratto;
        }

        if ($customerName !== null) {
            $payload['parametriAggiuntivi']['nome'] = $customerName;
        }
        if ($customerSurname !== null) {
            $payload['parametriAggiuntivi']['cognome'] = $customerSurname;
        }
        if ($customerEmail !== null) {
            $payload['parametriAggiuntivi']['mail'] = $customerEmail;
        }
        if ($incrementId !== null) {
            $payload['parametriAggiuntivi']['Note1'] = 'Ordine #'.(string)$incrementId;
        }

        return $payload;
    }

    /**
     * Execute pagaNonce API call to charge a tokenised card nonce
     *
     * @see https://ecommerce.nexi.it/specifiche-tecniche/build/pagamento.html
     * @param string      $codiceTransazione
     * @param int         $importo            Amount in minor units (cents)
     * @param int|string  $divisa             ISO 4217 numeric currency code
     * @param string      $xpayNonce
     * @param string      $accountingType     'C' for immediate capture (TCONTAB=C) or 'D' for deferred (TCONTAB=D)
     * @param string|null $customerName
     * @param string|null $customerSurname
     * @param string|null $customerEmail
     * @return array
     * @throws Mage_Core_Exception
     */
    public function pagaNonce(
        $codiceTransazione,
        $importo,
        $divisa,
        $xpayNonce,
        $accountingType,
        $customerName    = null,
        $customerSurname = null,
        $customerEmail   = null,
        $incrementId     = null
    ) {
        $payload = $this->_buildNoncePayload(
            $codiceTransazione,
            $importo,
            $divisa,
            $xpayNonce,
            $accountingType,
            $customerName,
            $customerSurname,
            $customerEmail,
            $incrementId
        );

        return $this->_doPost(self::XPAY_URI_PAGA_NONCE, $payload);
    }

    /**
     * Execute pagaNonceCreazioneContratto API call to charge a nonce and
     * simultaneously create a OneClick contract on XPay.
     *
     * @see https://ecommerce.nexi.it/specifiche-tecniche/build/pagamentooneclick/primopagamento.html
     * @param string      $codiceTransazione
     * @param int         $importo            Amount in minor units (cents)
     * @param int|string  $divisa             ISO 4217 numeric currency code
     * @param string      $xpayNonce
     * @param string      $numeroContratto    Unique contract ID (max 30 chars)
     * @param string      $accountingType     'C' for immediate capture (TCONTAB=C) or 'D' for deferred (TCONTAB=D)
     * @param string|null $customerName
     * @param string|null $customerSurname
     * @param string|null $customerEmail
     * @return array  Response with esito, codAut, brand, pan, scadenza
     * @throws Mage_Core_Exception
     */
    public function pagaNonceCreazioneContratto(
        $codiceTransazione,
        $importo,
        $divisa,
        $xpayNonce,
        $numeroContratto,
        $accountingType,
        $customerName    = null,
        $customerSurname = null,
        $customerEmail   = null,
        $incrementId     = null
    ) {
        $payload = $this->_buildNoncePayload(
            $codiceTransazione,
            $importo,
            $divisa,
            $xpayNonce,
            $accountingType,
            $customerName,
            $customerSurname,
            $customerEmail,
            $incrementId,
            $numeroContratto
        );

        return $this->_doPost(self::XPAY_URI_PAGA_NONCE_CREA_CONTRATTO, $payload);
    }

    /**
     * Execute pagamentoRicorrente3DS API call to charge a saved OneClick contract.
     *
     * @see https://ecommerce.nexi.it/specifiche-tecniche/servertoserver/pagamentoricorrente-pagamento1click/pagamentosuccessivo3dsecure/pagamento.html
     * @param string      $codiceTransazione
     * @param int         $importo            Amount in minor units (cents)
     * @param int|string  $divisa             ISO 4217 numeric currency code
     * @param string      $xpayNonce          Nonce returned by XPay for the recurring charge
     * @param string      $accountingType     'C' for immediate capture (TCONTAB=C) or 'D' for deferred (TCONTAB=D)
     * @param string|null $customerName
     * @param string|null $customerSurname
     * @param string|null $customerEmail
     * @param string|null $incrementId
     * @return array  Same format as pagaNonce response
     * @throws Mage_Core_Exception
     */
    public function pagamentoRicorrente3DS(
        $codiceTransazione,
        $importo,
        $divisa,
        $xpayNonce,
        $accountingType,
        $customerName    = null,
        $customerSurname = null,
        $customerEmail   = null,
        $incrementId     = null
    ) {

        $payload = $this->_buildNoncePayload(
            $codiceTransazione,
            $importo,
            $divisa,
            $xpayNonce,
            $accountingType,
            $customerName,
            $customerSurname,
            $customerEmail,
            $incrementId
            // numeroContratto is not needed for recurring payments
        );

        return $this->_doPost(self::XPAY_URI_RICORRENTE_3DS, $payload);
    }
    
    /**
     * Core cURL POST method
     *
     * @param string $endpoint Relative endpoint (e.g., self::XPAY_URI_PAGA_NONCE)
     * @param array  $payload
     * @return array Decoded JSON response
     * @throws Mage_Core_Exception
     */
    protected function _doPost($endpoint, $payload)
    {
        $helper = $this->_helper;
        $baseUrl = $helper->getXpayBaseUrl();
        $url = $baseUrl . $endpoint;

        $helper->log('XpayClient POST ' . $url . ' payload: ' . json_encode($this->_sanitizeForLog($payload)));

        $connection = curl_init();
        curl_setopt_array($connection, array(
            CURLOPT_URL            => $url,
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));

        $rawResponse = curl_exec($connection);
        $httpCode    = curl_getinfo($connection, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($connection);
        curl_close($connection);

        if ($rawResponse === false || $curlError !== '') {
            $msg = 'XpayClient cURL error for ' . $url . ': ' . $curlError;
            $helper->log($msg, Zend_Log::ERR);
            Mage::throwException($msg);
        }

        $helper->log('XpayClient response [HTTP ' . $httpCode . ']: ' . $rawResponse);

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = 'XpayClient received HTTP ' . $httpCode . ' from ' . $url;
            $helper->log($msg, Zend_Log::ERR);
            Mage::throwException($msg);
        }

        $decoded = json_decode($rawResponse, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $msg = 'XpayClient could not decode JSON response from ' . $url;
            $helper->log($msg, Zend_Log::ERR);
            Mage::throwException($msg);
        }
        if ($decoded['esito'] !== 'OK') {
            $msg = 'Errore da XPay API';
            if (isset($decoded['errore']['messaggio'])) {
                $msg .= ': '.$decoded['errore']['messaggio'];
            }
            $helper->log($msg, Zend_Log::ERR);
        }

        // Verify response MAC - always required
        $requiredMacFields = array('mac', 'esito', 'idOperazione', 'timeStamp');
        foreach ($requiredMacFields as $field) {
            if (!isset($decoded[$field])) {
                $msg = 'XpayClient response missing required field: ' . $field;
                $helper->log($msg, Zend_Log::ERR);
                Mage::throwException($msg);
            }
        }

        $macKey = $helper->getXpayMacKey();
        $macHelper = Mage::helper('nexi_xpaybuild/mac');

        $isValidMac = $macHelper->verifyResponseMac(
            $decoded['mac'],
            $decoded['esito'],
            $decoded['idOperazione'],
            $decoded['timeStamp'],
            $macKey
        );

        if (!$isValidMac) {
            $msg = 'XpayClient: MAC verification failed for ' . $url;
            $helper->log($msg, Zend_Log::ERR);
            Mage::throwException($msg);
        }
        $helper->log('XpayClient: MAC verification succeeded for ' . $url);

        return $decoded;
    }

    /**
     * Return a copy of $payload with sensitive keys redacted, safe for logging.
     *
     * @param array $payload
     * @return array
     */
    protected function _sanitizeForLog(array $payload)
    {
        $sensitive = array('apiKey', 'mac');
        $sanitized = $payload;
        foreach ($sensitive as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '***REDACTED***';
            }
        }
        return $sanitized;
    }
}
