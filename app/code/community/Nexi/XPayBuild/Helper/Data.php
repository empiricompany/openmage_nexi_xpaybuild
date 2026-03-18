<?php
class Nexi_XPayBuild_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH = 'payment/nexi_xpaybuild/';

    /**
     * @param string $field
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfig($field, $storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH . $field, $storeId);
    }

    /**
     * Get the gateway type. Always returns 'XPAY' (only supported gateway).
     *
     * @param int|null $storeId
     * @return string  'XPAY'
     */
    public function getGatewayType($storeId = null)
    {
        return 'XPAY';
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getXpayEnvironment($storeId = null)
    {
        return $this->getConfig('environment', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getXpayAlias($storeId = null)
    {
        return $this->getConfig('alias', $storeId);
    }

    /**
     * Get XPay MAC key (decrypted)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getXpayMacKey($storeId = null)
    {
        $macKey = $this->getConfig('mac_key', $storeId);
        return Mage::helper('core')->decrypt($macKey);
    }

    /**
     * Get accounting type — I = Immediate, D = Deferred.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAccountingType($storeId = null)
    {
        $type = $this->getConfig('accounting_type', $storeId);
        return ($type === 'D') ? 'D' : 'I';
    }

    /**
     * Get XPay card form style (SPLIT_CARD | CARD)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCardFormStyle($storeId = null)
    {
        $style = $this->getConfig('card_form_style', $storeId);
        return ($style === 'SPLIT_CARD') ? 'SPLIT_CARD' : 'CARD';
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getOneclickEnabled($storeId = null)
    {
        return (bool)$this->getConfig('oneclick_enabled', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getXpayBaseUrl($storeId = null)
    {
        $env = $this->getXpayEnvironment($storeId);
        if ($env === 'test') {
            return 'https://int-ecommerce.nexi.it/';
        }
        return 'https://ecommerce.nexi.it/';
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getXpaySdkUrl($storeId = null)
    {
        $baseUrl = $this->getXpayBaseUrl($storeId);
        $alias = $this->getXpayAlias($storeId);
        return $baseUrl . 'ecomm/XPayBuild/js?alias=' . urlencode($alias);
    }

    /**
     * Returns preconnect + preload hints + <script> tags for XPay Build.
     * Called via layout XML: <action method="setText"><text helper="nexi_xpaybuild/data/getPaymentJsHtml"/></action>
     *
     * @return string
     */
    public function getPaymentJsHtml()
    {
        $jsFile = Mage::getDesign()->getFilename('nexi/xpaybuild/js/nexi_payment.js', ['_type' => 'skin']);
        $ts = file_exists($jsFile) ? filemtime($jsFile) : time();

        $origin = rtrim($this->getXpayBaseUrl(), '/');
        $sdkUrl = htmlspecialchars($this->getXpaySdkUrl(), ENT_QUOTES);
        $jsUrl  = htmlspecialchars(
            Mage::getDesign()->getSkinUrl('nexi/xpaybuild/js/nexi_payment.js') . '?timestamp=' . $ts,
            ENT_QUOTES
        );

        return '<link rel="preconnect" href="' . $origin . '" crossorigin>' . "\n"
             . '<link rel="dns-prefetch" href="' . $origin . '">' . "\n"
             . '<link rel="preload" href="' . $sdkUrl . '" as="script" crossorigin>' . "\n"
             . '<script type="text/javascript" src="' . $sdkUrl . '"></script>' . "\n"
             . '<script type="text/javascript" src="' . $jsUrl . '"></script>' . "\n";
    }

    /**
     * Format amount to minor unit (cents)
     *
     * @param float $amount
     * @param string $currencyCode
     * @return int
     */
    public function formatAmountToMinorUnit($amount, $currencyCode = 'EUR')
    {
        return (int)round($amount * 100);
    }

    /**
     * @param string|null $currencyCode
     * @return string
     */
    public function getCurrencyNumericCode($currencyCode = null)
    {
        if ($currencyCode === null) {
            $currencyCode = $this->getStoreCurrencyCode();
        }

        $map = array(
            'EUR' => '978',
            'USD' => '840',
            'GBP' => '826',
            'CHF' => '756',
            'JPY' => '392',
        );

        return isset($map[$currencyCode]) ? $map[$currencyCode] : '978';
    }

    /**
     * @return string
     */
    public function getStoreCurrencyCode()
    {
        return Mage::app()->getStore()->getCurrentCurrencyCode();
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function generateTransactionId($order)
    {
        return substr($order->getIncrementId() . '-' . time(), 0, 30);
    }

    /**
     * @param string $message
     * @param int $level
     */
    public function log($message, $level = Zend_Log::DEBUG)
    {
        Mage::log($message, $level, 'nexi_xpaybuild.log', true);
    }

    /**
     * @return string
     */
    public function getModuleVersion()
    {
        return (string)Mage::getConfig()->getNode('modules/Nexi_XPayBuild/version');
    }

    /**
     * Create an offline invoice for an order and save it via a resource transaction.
     *
     * @param Mage_Sales_Model_Order $order
     * @param string                 $transactionId  Optional transaction ID to stamp on the invoice
     * @return void
     */
    public function createInvoiceForOrder(Mage_Sales_Model_Order $order, $transactionId = '')
    {
        if (!$order->canInvoice()) {
            $this->log(
                'Helper/Data::createInvoiceForOrder — cannot invoice order ' . $order->getId(),
                Zend_Log::WARN
            );
            return;
        }

        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $order->prepareInvoice();
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $invoice->register();

        if ($transactionId) {
            $invoice->setTransactionId($transactionId);
        }

        Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();

        $this->log(
            'Helper/Data::createInvoiceForOrder — invoice ' . $invoice->getIncrementId()
            . ' created for order ' . $order->getId()
        );

        if (!$order->getEmailSent()) {
            try {
                $order->sendNewOrderEmail();
                $order->setEmailSent(true);
                $order->save();
                $this->log('Helper/Data::createInvoiceForOrder — new order email sent for order ' . $order->getId());
            } catch (Exception $e) {
                $this->log(
                    'Helper/Data::createInvoiceForOrder — sendNewOrderEmail error: ' . $e->getMessage(),
                    Zend_Log::WARN
                );
            }
        }
    }

    /**
     * Format card expiry from YYYYMM format to locale-appropriate display
     *
     * @param string $expiry Expiry in YYYYMM format (e.g., "202612")
     * @return string
     */
    public function formatExpiry($expiry)
    {
        if (empty($expiry) || !preg_match('/^(\d{4})(\d{2})$/', $expiry, $matches)) {
            return $expiry;
        }

        $year  = (int)$matches[1];
        $month = (int)$matches[2];

        $date = new Zend_Date();
        $date->setYear($year);
        $date->setMonth($month);
        $date->setDay(1);

        $locale = Mage::app()->getLocale()->getLocaleCode();
        $format = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM);

        $format = preg_replace('/[dD]+[^\/]*\/?/', '', $format);
        $format = preg_replace('/\/[dD]+[^\/]*/', '', $format);
        $format = trim($format, '/ ');

        if (empty($format)) {
            $format = 'MM/yyyy';
        }

        return $date->toString($format, null, $locale);
    }

    /**
     * Get platform signature string for API requests.
     *
     * @return string
     */
    public function getPluginSignature()
    {
        if (method_exists('Mage', 'getOpenMageVersion')) {
            $platform = 'openmage';
            $platformVersion = Mage::getOpenMageVersion();
        } else {
            $platform = 'magento';
            $platformVersion = Mage::getVersion();
        }
        
        $pluginVersion = $this->getModuleVersion();
        
        return 'Platform: ' . $platform . ' ' . $platformVersion . ' - PluginVersion: ' . $pluginVersion;
    }

}
