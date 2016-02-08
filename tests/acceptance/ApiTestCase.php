<?php namespace Topor;

use Topor\SandboxAdminServer as Admin;
use Topor\SandboxClientServer as Client;
use GuzzleHttp\Client as Guzzle;

class ApiTestCase extends \PHPUnit_Framework_TestCase
{
    protected $base_url;
    protected $admin_url;
    protected $login;
    protected $password;

    protected function generateCardData()
    {
        return [
            'number' => '5417-1503-9627-6825',
            'expire_month' => 1,
            'expire_year' => 2017,
            'cvv' => 789,
            'cardholder' => 'TESTER TESTEROV'
        ];
    }

    /**
     * @param $url
     * @param $card
     * @return bool
     */
    protected function postCardAtUrl($url, $card)
    {
        $client = new Guzzle();

        $client->setDefaultOption(
            'headers',
            [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Cache-Control' => 'max-age=0',
                'Connection' => 'keep-alive',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:30.0) Gecko/20100101 Firefox/30.0',
            ]
        );

        $url_struct = parse_url($url);
        parse_str($url_struct['query'], $url_params);

        $resp = $client->get($url);
        $resp_body = (string)$resp->getBody();

        if(!preg_match("/name='sessid' value='(.+)'\/>/", $resp_body, $matches))
            return $this->fail("Sessid not found in iPSP response");

        $sessid = $matches[1];
        $endpoint = sprintf('%s://%s%s', $url_struct['scheme'], $url_struct['host'], $url_struct['path']);

        $params = [
            'cardholder' => $card['cardholder'],
            'cvv' => $card['cvv'],
            'exp_date_m' => $card['expire_month'],
            'exp_date_y' => $card['expire_year'],
            'iframe' => 'false',
            'pan' => $card['number'],
            'sessid' => $sessid,
            'hidden_lang' => '',
            'hidden_time' => ''
        ];
        if (array_key_exists('biller_client_id', $url_params)) {
            $params['biller_client_id'] = $url_params['biller_client_id'];
        }
        if (array_key_exists('perspayee_expiry', $url_params)) {
            $params['perspayee_expiry'] = $url_params['perspayee_expiry'];
        }
        if (array_key_exists('recur_freq', $url_params)) {
            $params['recur_freq'] = $url_params['recur_freq'];
        }

        $resp = $client->post($endpoint, ['body' => $params]);
        $resp_body = (string) $resp->getBody();

        if (preg_match('/Ваш платеж одобрен/', $resp_body, $matches)) {
            return true;
        }

        return false;
    }

    /**
     * @param null $phone
     * @param string $password
     * @return object
     */
    protected function createAccount($phone = null, $password = 'mbankphp')
    {
        $phone = $phone ?: '+799999'.rand(11111, 99999);

        $resp = $this->getAdminClient()->account($phone);
        if ($resp) {
            $this->getAdminClient()->deleteAccount($phone);
        }

        $client = new Client($phone, $password);
        $code = $client->createAccount()->dev->security_code;
        $response = $client->activateAccount($code);
        $this->assertEquals(200, $response->meta->code);

        return (object) [
            'phone' => $phone,
            'password' => $password
        ];
    }

    function fillPersonalInfo(SandboxClientServer $client)
    {
        return $client->fillPersonalInfo([
            "family_name" => "Арсеньев",
            "given_name" => "Алексей",
            "patronymic_name" => "Александрович",
            "passport_series_number" => "2202655885",
            "passport_issued_at" => "2012-02-27",
            "itn" => "330500938709",
            "ssn" => "11223344595"
        ]);
    }

    function getAdminClient()
    {
        return new Admin;
    }

    function defaultClient($force_recreation = false)
    {
        $credentials = $this->defaultClientCredentials();
        if($force_recreation)
        {
            $this->createAccount($credentials->phone, $credentials->password);
        }
        return new Client($credentials->phone, $credentials->password);
    }

    function defaultClientCredentials()
    {
        return (object) [
            'phone' => '+79270000001',
            'password' => 'mbankphp'
        ];
    }

    function anotherUserClient($force_recreation = false)
    {
        $phone = '+79270000002';
        $password = 'mbankphp';
        if($force_recreation)
        {
            $this->createAccount($phone, $password);
        }
        return new Client($phone, $password);
    }

    /**
     * @param $account
     * @return Client
     */
    function client($account)
    {
        return new Client($account->phone, $account->password);
    }
}
