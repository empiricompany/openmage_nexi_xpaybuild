<?php
class Nexi_XPayBuild_Helper_Mac extends Mage_Core_Helper_Abstract
{
    /**
     * Calculate init MAC for XPay SDK initialization
     *
     * @param string $codTrans
     * @param string $divisa
     * @param int $importo
     * @param string $macKey
     * @return string
     */
    public function calculateInitMac($codTrans, $divisa, $importo, $macKey)
    {
        $strMac = 'codTrans=' . $codTrans . 'divisa=' . $divisa . 'importo=' . $importo . $macKey;
        return sha1($strMac);
    }

    /**
     * Calculate MAC for pagaNonce API call
     *
     * @param string $apiKey
     * @param string $codTrans
     * @param int $importo
     * @param string $divisa
     * @param string $xpayNonce
     * @param string $timeStamp
     * @param string $macKey
     * @return string
     */
    public function calculateNonceMac($apiKey, $codTrans, $importo, $divisa, $xpayNonce, $timeStamp, $macKey)
    {
        $strMac = 'apiKey=' . $apiKey .
                  'codiceTransazione=' . $codTrans .
                  'importo=' . $importo .
                  'divisa=' . $divisa .
                  'xpayNonce=' . $xpayNonce .
                  'timeStamp=' . $timeStamp .
                  $macKey;
        return sha1($strMac);
    }

    /**
     * Verify API response MAC using timing-safe comparison
     *
     * Response MAC formula for all endpoints: esito + idOperazione + timeStamp + macKey
     *
     * @param string $receivedMac
     * @param string $esito
     * @param string $idOperazione
     * @param string $timeStamp
     * @param string $macKey
     * @return bool
     */
    public function verifyResponseMac($receivedMac, $esito, $idOperazione, $timeStamp, $macKey)
    {
        $strMac = 'esito=' . $esito .
                  'idOperazione=' . $idOperazione .
                  'timeStamp=' . $timeStamp .
                  $macKey;
        $calculatedMac = sha1($strMac);
        return hash_equals($calculatedMac, $receivedMac);
    }

    /**
     * Calculate MAC for accounting operations (capture/refund)
     *
     * @param string $apiKey
     * @param string $codiceTransazione
     * @param int $importo
     * @param string $divisa
     * @param string $timeStamp
     * @param string $macKey
     * @return string
     */
    public function calculateAccountingMac($apiKey, $codiceTransazione, $importo, $divisa, $timeStamp, $macKey)
    {
        $strMac = 'apiKey=' . $apiKey .
                  'codiceTransazione=' . $codiceTransazione .
                  'divisa=' . $divisa .
                  'importo=' . $importo .
                  'timeStamp=' . $timeStamp .
                  $macKey;
        return sha1($strMac);
    }

    /**
     * Calculate MAC for orderDetail (situazioneOrdine) API call.
     *
     * String signed: apiKey={v}codiceTransazione={v}timeStamp={v}{macKey}
     *
     * @param string $apiKey
     * @param string $codiceTransazione
     * @param string $timeStamp  Millisecond timestamp (13 digits)
     * @param string $macKey
     * @return string SHA1 hex string
     */
    public function calculateOrderDetailMac($apiKey, $codiceTransazione, $timeStamp, $macKey)
    {
        $strMac = 'apiKey=' . $apiKey .
                  'codiceTransazione=' . $codiceTransazione .
                  'timeStamp=' . $timeStamp .
                  $macKey;
        return sha1($strMac);
    }

    /**
     * Calculate MAC for profileInfo API call.
     *
     * String signed: apiKey={v}timeStamp={v}{macKey}
     *
     * @param string $apiKey
     * @param string $timeStamp  Millisecond timestamp (13 digits)
     * @param string $macKey
     * @return string SHA1 hex string
     */
    public function calculateProfileInfoMac($apiKey, $timeStamp, $macKey)
    {
        $strMac = 'apiKey=' . $apiKey .
                  'timeStamp=' . $timeStamp .
                  $macKey;
        return sha1($strMac);
    }
}
