<?php namespace Topor;

use GuzzleHttp\Client as Guzzle;
use Rhumsaa\Uuid\Uuid;
use Topor\Best\Exception\FeeNotFound;
use Topor\Best\Exception\TransferNotFound;
use Topor\Best\XMLSecurityDSig;
use Topor\Best\XMLSecurityKey;

class Best extends AbstractClient
{
    const ENV_DEV = 'https://test.bestmt.ru/v1/s/bsi.dll';
    const ENV_PROD = 'https://pay.bestmt.ru/v82/s/bsi.dll';

    protected $private_key;
    protected $public_key;

    protected $partner_id;
    protected $member_id;
    protected $system_name = 'БЭСТ';

    public function setCredentials($partner_id, $member_id, $private_key, $public_key, $system_name = null)
    {
        $this->partner_id = $partner_id;
        $this->member_id = $member_id;
        $this->private_key = file_get_contents($private_key);
        $this->public_key = file_get_contents($public_key);
        if ($system_name) {
            $this->system_name = $system_name;
        }
    }

    public function requestDictionary($source_name, $chunk_number = null)
    {
        $guid = strtoupper((string) Uuid::uuid1());
        $date = date(DATE_ATOM); //2014-11-27T11:11:00.0Z
        $data_xml = $this->createDirectoryXml($source_name, $chunk_number);
        $request_xml = $this->generateRequestXML($data_xml, $guid, $date);
        $body = $this->post($request_xml);
        return $body;
    }

    public function downloadDictionaryToFile($source_name)
    {
        $guid = strtoupper((string) Uuid::uuid1());
        $date = date(DATE_ATOM); //2014-11-27T11:11:00.0Z
        $data_xml = $this->createDirectoryXml($source_name);
        $request_xml = $this->generateRequestXML($data_xml, $guid, $date);
        $content = (string) $this->post($request_xml)->getBody();
        $filename = storage_path('best/'.$source_name.'.xml');
        file_put_contents($filename, $content);
        return $filename;
    }

    /**
     * @param int $amount
     * @param string $from_currency
     * @param string $to_currency
     * @param int $to_country
     * @param int $system_id
     * @return object
     */
    public function fee($amount, $from_currency, $to_currency, $to_country, $system_id = 0)
    {
        $amount = (int) $amount;
        $to_country = sprintf("%03d", $to_country);
        $system_id = (int) $system_id;
        $guid = strtoupper((string) Uuid::uuid1());
        $date = date(DATE_ATOM); //2014-11-27T11:11:00.0Z
        $data_xml = <<<EOD
    <transfer Id="DocData">
        <fee-request
            amount-charge="$amount"
            currency-charge="$from_currency"
            currency-alpha="$to_currency"
            creating-date="$date"
            system-id="{$system_id}">
            <country numeric="{$to_country}"/>
        </fee-request>
    </transfer>
EOD;
        $request_xml = $this->generateRequestXML($data_xml, $guid, $date);
        $resp = $this->post($request_xml);
        $resp_json = $this->xmlToObj($resp->getBody());

        if (property_exists($resp_json, 'error')) {
            $code = $resp_json->error->attrs->code;
            $text = $resp_json->error->attrs->text;
            switch ($code) {
                case (1002):
                    //Не найдено ни одной подходящей комиссии
                    throw new FeeNotFound($text);
                case (1028):
                    //Запрещены переводы в валюте
                    throw new FeeNotFound($text);
                default:
                    throw new \RuntimeException($text);
            }
        }

        /**
         * Этот закуток ада нужен
         * для поддержки разных версий/настроек libxml
         */
        $resp_transfer = $resp_json->transfer;
        if (property_exists($resp_transfer, '0')) {
            $fee_reply = $resp_transfer->{'0'}->attrs;
        } elseif (property_exists($resp_transfer, 'fee-reply'))
            $fee_reply = $resp_transfer->{'fee-reply'}->attrs;
        else {
            throw new FeeNotFound('Комиссия не найдена');
        }

        return (object) [
            'amount' => (float) $fee_reply->amount,
            'fee' => (float) $fee_reply->fee,
            'rate' => (float) $fee_reply->rate
        ];
    }

    /**
     * @param $control_number
     * @return object
     * @throws Best\Exception
     * @throws TransferNotFound
     */
    public function status($control_number)
    {
        $control_number = intval($control_number);
        $guid = strtoupper((string) Uuid::uuid1());
        $date = date(DATE_ATOM); //2014-11-27T11:11:00.0Z
        $data_xml = <<<EOD
    <transfer-status Id="DocData">
        <request checknumber="{$control_number}"/>
    </transfer-status>
EOD;
        $request_xml = $this->generateRequestXML($data_xml, $guid, $date);
        $resp = $this->post($request_xml);
        $resp_json = $this->xmlToObj($resp->getBody());

        if (property_exists($resp_json, 'error')) {
            $code = $resp_json->error->attrs->code;
            $text = $resp_json->error->attrs->text;
            switch ($code) {
                case (1005):
                    //Не найдено ни одной подходящей комиссии
                    throw new TransferNotFound($text);
                default:
                    throw new \RuntimeException($text.': '.$data_xml);
            }
        }

        $reply = $resp_json->{'transfer-status'}->{'reply'}->transfer->attrs;

        return (object) [
            'code' => $reply->{'status-code'},
            'text' => $reply->{'status-text'},
            'time' => $reply->{'status-last-change-time'}
        ];
    }

    public function createDirectoryXml($source_name, $chunk_number = null)
    {
        $data_xml = "<directory Id=\"DocData\">";
        $data_xml .= "<request type=\"$source_name\" change-date=\"1900-01-01T00:00:00.0Z\"";
        if ($chunk_number) {
            $data_xml .= " chunk-number=\"$chunk_number\"";
        }
        $data_xml .= "></request></directory>";
        return $data_xml;
    }

    public function generateRequestXML($data_xml, $guid, $date = null)
    {
        $data_xml = <<<EOD
<?xml version="1.0" encoding="windows-1251" standalone="no"?>
    <document
        xmlns:ns2="http://www.w3.org/2000/09/xmldsig#"
        datetime="$date"
        guid="$guid"
        partner-id="{$this->partner_id}"
        member-id="{$this->member_id}"
        system="{$this->system_name}"
        version="1.7">
        $data_xml
    </document>
EOD;
        $data_xml = preg_replace('~\s*(<([^>]*)>[^<]*</\2>|<[^>]*>)\s*~', '$1', $data_xml);
        $data_xml = iconv('UTF-8', 'Windows-1251', $data_xml);

        $doc = new \DOMDocument();
        $doc->loadXML($data_xml);
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);
        $objDSig->addReference(
            $doc->documentElement->firstChild,
            XMLSecurityDSig::SHA1,
            [XMLSecurityDSig::C14N],
            [ 'overwrite' => false ]
        );
        $objKey = new XMLSecurityKey(
            XMLSecurityKey::RSA_SHA1,
            ['type' => 'private']
        );
        $objKey->loadKey($this->private_key);
        $objDSig->sign($objKey);
        $objDSig->add509Cert($this->public_key);
        $objDSig->appendSignature($doc->documentElement);

        return $doc->saveXML();
    }

    public function post($request_xml, $data = [])
    {
        return $this->transport()->post($this->base_url, [
            'body' => $request_xml
        ]);
    }

    public function createTransport($base_url)
    {
        $handler = $this->attachMiddleware();

        $transport = new Guzzle([
            'base_url' => $base_url,
            'headers' => [
                'Rts-Request' => 'T=abAPIChara.ComSession',
                'Content-Type' => 'text/xml;charset=Windows-1251'
            ],
            'timeout' => 60,
            'connect_timeout' => 5,
            'handler' => $handler,
            'http_errors' => false
        ]);
        return $transport;
    }

    protected function xmlToObj($xml)
    {
        $json = json_encode((array) simplexml_load_string($xml));
        $json = str_replace('@attributes', 'attrs', $json);
        return json_decode($json, false);
    }
}
