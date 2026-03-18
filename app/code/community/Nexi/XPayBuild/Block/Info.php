<?php
class Nexi_XPayBuild_Block_Info extends Mage_Payment_Block_Info
{
    /**
     * Card brand (e.g. 'VISA', 'MASTERCARD').
     *
     * @return string
     */
    public function getCardBrand()
    {
        return (string)$this->getInfo()->getAdditionalInformation('xpay_brand');
    }

    /**
     * Last 4 digits extracted from the masked PAN stored by XPay.
     * XPay stores the PAN as e.g. "1234XXXXXXXX5678" — we take the rightmost 4 chars.
     *
     * @return string  4-digit string, or empty string if unavailable.
     */
    public function getCardLast4()
    {
        $pan = (string)$this->getInfo()->getAdditionalInformation('xpay_pan');
        if (strlen($pan) >= 4) {
            return substr($pan, -4);
        }
        return '';
    }

    /**
     * Card expiry as stored by XPay (e.g. '12/26').
     *
     * @return string
     */
    public function getCardExpiry()
    {
        $scadenza = (string)$this->getInfo()->getAdditionalInformation('xpay_scadenza');
        if (!$scadenza) {
            $scadenza = (string)$this->getInfo()->getAdditionalInformation('xpay_scadenzaPan');
        }
        return $scadenza;
    }

    /**
     * XPay authorization code (codAut).
     *
     * @return string
     */
    public function getAuthCode()
    {
        return (string)$this->getInfo()->getAdditionalInformation('xpay_codAut');
    }

    /**
     * XPay transaction code (codTrans / internal order reference).
     *
     * @return string
     */
    public function getTransactionId()
    {
        return (string)$this->getInfo()->getAdditionalInformation('xpay_cod_trans');
    }

    /**
     * Prepare specific information for the default OpenMage info table.
     * Used as fallback and for email order confirmations.
     * Does NOT expose the raw nonce.
     *
     * @param Varien_Object|null $transport
     * @return Varien_Object
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $info = $this->getInfo();
        $data = array();

        $brand  = $this->getCardBrand();
        $last4  = $this->getCardLast4();
        $expiry = $this->getCardExpiry();

        $formattedExpiry = '';
        if ($expiry) {
            $formattedExpiry = Mage::helper('nexi_xpaybuild')->formatExpiry($expiry);
        }

        $isSavedCard = (int)$info->getAdditionalInformation('saved_card_id') > 0;

        if ($brand || $last4) {
            $cardLine = trim($brand . ($last4 ? ' •••• ' . $last4 : ''));
            if ($formattedExpiry) {
                $cardLine .= '  ' . $formattedExpiry;
            }
            if ($isSavedCard) {
                $cardLine .= ' (Carta salvata)';
            }
            $data[(string)$this->__('Card')] = $cardLine;
        }

        if ($txId = $this->getTransactionId()) {
            $data[(string)$this->__('Transaction ID')] = $txId;
        }

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
