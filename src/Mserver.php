<?php namespace Topor;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use Rhumsaa\Uuid\Uuid;
use GuzzleHttp\Client as Guzzle;

class Mserver extends AbstractClient
{
    const ENV_DEV = 'https://www.synq.ru/mserver2-dev/';
    const ENV_STAGE = 'https://www.synq.ru/mserver2-stage/';
    const ENV_PROD = 'https://www.synq.ru/mserver2-prod/';

    const REASON = 'invalid_data';

    protected $app_login;
    protected $app_password;
    protected $user_login = 'admin';
    protected $user_password = 'admin';

    public function setAppAuth($login, $password)
    {
        $this->app_login = $login;
        $this->app_password = $password;
    }

    public function getUserAgent()
    {
        return $this->app_login;
    }

    /**
     * @param string $sort
     * @param array $filters
     * @param int $page
     * @param int $size
     * @return ResponseInterface|mixed|null
     *
     * Поля сортировки
     * paymentCount - по количеству платежей
     * turnover - по обороту
     * activeCardCount - по количеству привязанных карт
     * totalCardCount - по количеству привязанных карт (с уже удаленными)
     * createdAt - по дате регистрации
     * amount - по остаткам
     *
     * Поля для фильтрации:
     * ipAddress - по ip адресу
     * givenName familyName patronymicName - по ФИО, поиск полного совпадения
     * или совпадения в начале
     * phone - по номеру телефона, поиск полного совпадения или совпадения в начале
     * cardNumber - по бин+номер карты - чтобы искать пользователей,
     * у которых была привязана эта же карта, поиск любых совпадений внутри номера
     * active - по статусу активации (true|false)
     */
    public function accounts($sort = 'createdAt,desc', $filters = [], $page = 0, $size = 35)
    {
        $params = array_merge($filters, [
            'sort' => $sort,
            'page' => $page,
            'size' => $size
        ]);
        return $this->get('wallets/?'.http_build_query($params));
    }

    public function account($phone)
    {
        try {
            return $this->get('wallets/'.$phone);
        } catch (ClientException $e) {
            $resp = json_decode((string)$e->getResponse()->getBody());
            if ('wallet_not_found' == $resp->meta->error) {
                return null;
            } else {
                throw $e;
            }
        }
    }

    public function deleteAccount($phone)
    {
        return $this->delete('wallets/'.urlencode($phone));
    }

    public function markAccountAsVerified($phone)
    {
        return $this->post(
            'persons/'.urlencode($phone).'/update_status',
            ['status' => self::PERSONIFIED]
        );
    }

    protected function adminTransport()
    {
        return new Guzzle([
            'base_url' => 'https://www.synq.ru/mserver2-dev/admin/',
            'defaults' => [
                'auth' => [$this->user_login, $this->user_password],
                'headers' => [
                    'User-Agent' => 'MBankPHP',
                    'Content-Type' => 'application/json',
                ]
            ],
        ]);
    }

    public function paymentFromCardToService($service_id, $amount, $parameters, $client_payment_id = null)
    {
        $client_payment_id = $client_payment_id ?: (string) Uuid::uuid1();
        $data = [
            'type' => self::PAY_CARD_TO_SERVICE,
            'service' => $service_id,
            'client_payment_id' => $client_payment_id,
            'amount' => $amount,
            'parameters' => $parameters,
        ];
        $response_data = $this->post('application/payments', $data);
        return $response_data;
    }

    public function updatePayment($payment_id, $parameters, $client_payment_id = null)
    {
        $client_payment_id = $client_payment_id ?: (string) Uuid::uuid1();
        $data = [
            'client_payment_id' => $client_payment_id,
            'parameters' => $parameters,
        ];
        $response_data = $this->post('application/payments/'.$payment_id, $data);
        return $response_data;
    }

    public function approvePayment($payment_id, $success_back_url, $failure_back_url, $iframe = false)
    {
        $response_data = $this->post('application/payments/'.$payment_id.'/pay', [
            'card_success_url' => $success_back_url,
            'card_failure_url' => $failure_back_url,
            'iframe' => $iframe
        ]);
        return $response_data;
    }

    public function payment($payment_id)
    {
        $response_data = $this->get('application/payments/'.$payment_id);
        $status = $response_data->data->status;
        $next_action = $response_data->meta->next_action;

        if (self::CREATED == $status && 'get' == $next_action) {
            $response_data->data->status = self::CREATING;
        }

        return $response_data;
    }

    /**
     * @param     $payment_id
     * @param     $next_action
     * @param     $attempts
     * @param int $sleep
     *
     * @return null|object
     */
    public function waitForNextAction($payment_id, $next_action, $attempts, $sleep = 2)
    {
        if (!$attempts) {
            return null;
        }

        sleep($sleep);

        $resp = $this->payment($payment_id);

        if (Mserver::DECLINED == $resp->data->status ||
            $next_action == $resp->meta->next_action
        ) {
            return $resp;
        }

        return $this->waitForNextAction($payment_id, $next_action, $attempts - 1, $sleep);
    }

    public function phoneService($phone)
    {
        if ('+7' == substr($phone, 0, 2)) {
            $phone = substr($phone, 2);
        }
        return $this->get('application/services/search?type=phone&input='.$phone);
    }

    public function service($id)
    {
        return $this->get('application/services/'.$id);
    }

    public function services()
    {
        return $this->get('application/services');
    }

    /**
     * @param string $base_url
     * @return Guzzle
     */
    public function createTransport($base_url)
    {
        $handler = $this->attachMiddleware();

        $transport = new Guzzle([
            'base_uri' => $base_url,
            'headers' => [
                'User-Agent' => $this->getUserAgent(),
                'Content-type' => 'application/json',
                'Authorization' => 'Bearer '.$this->requestToken()
            ],
            'timeout' => 10,
            'connect_timeout' => 10,
            'handler' => $handler,
            'http_errors' => false
        ]);

        return $transport;
    }

    public function requestToken()
    {
        return \Cache::remember(env('APP_PROJECT').'_app_token', 30, function () {

            $handler = static::attachMiddleware();

            $guzzle = new Guzzle([
                'base_uri' => $this->base_url,
                'auth' => [$this->app_login, $this->app_password],
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                    'Accept' => 'application/json',
                ],
                'form_params' => ['grant_type' => 'client_credentials'],
                'timeout' => 10,
                'connect_timeout' => 5,
                'handler' => $handler,
                'http_errors' => false
            ]);

            $response = $guzzle->post('oauth/token');
            $response_data = json_decode((string)$response->getBody());
            return $response_data->data->access_token;
        });
    }
}
