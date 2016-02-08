<?php namespace Topor;

use Concat\Http\Middleware\Logger;
use GuzzleHttp\Client as Guzzle;
use Topor\DTO\AutopayService;
use Topor\DTO\AutopayServiceList;
use Topor\DTO\AutopayTrigger;

class Autopay extends AbstractClient
{
    const ENV_DEV = 'https://www.synq.ru/ap-dev/v1/';
    const ENV_PROD = 'https://www.synq.ru/ap-prod/v1/';

    protected $app_login;
    protected $app_password;

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
     * Возвращает список сервисов, доступных для автоплатежа
     * @return Topor\DTO\AutopayServiceList Список сервисов, доступных для автоплатежа
     */
    public function services()
    {
        $response = $this->get('services');
        $services = AutopayServiceList::parseFromStdClass($response);
        return $services;
    }

    /**
     * Возвращает сервис из автоплатежа по id
     * @param  integer $service_id Id сервиса
     * @return Topor\DTO\AutopayServiceList Сервис из автоплатежа
     */
    public function getServiceById($service_id)
    {
        $response = $this->get('services/' . (int) $service_id);

        $service = AutopayService::parseFromStdClass($response->data);
        return $service;
    }

    public function createThresholdTrigger(AutopayService $service, $amount, array $parameters, $additional_info, $threshold)
    {
        $payload = [
            'service' => $service->getId(),
            'amount' => $amount,
            'parameters' => static::preparePaymentParamsForAutopay($parameters),
            'type' => 'threshold',
            'cf' => $additional_info,
            'threshold' => $threshold,
        ];

        $response = $this->post('triggers', $payload);
        $trigger = AutopayTrigger::parseFromStdClass($response->data);

        $trigger_creation_result = $this->waitForNextTriggerStatus($trigger->getId(), AutopayTrigger::ACTIVE, 3, 3);

        return $trigger_creation_result;
    }

    /**
     * Выполняет запрос на удаление триггера
     * @param  integer $trigger_id Идентификатор триггера
     * @return Topor\DTO\AutopayTrigger Триггер
     */
    public function deleteTrigger($trigger_id)
    {
        $response = $this->delete('triggers/' . (int) $trigger_id);
        $trigger = AutopayTrigger::parseFromStdClass($response->data);

        return $trigger;
    }

    /**
     * Ожидание нужного статуса у триггера
     * @param  integer  $trigger_id  Идентификатор триггера
     * @param  String  $next_status Ожидаемый статус триггера
     * @param  integer  $attempts    Количество попыток запроса, которые стоит предпринять
     * @param  integer $sleep       Время между запросами в секундах
     * @return Topor\DTO\AutopayTrigger Триггера
     */
    public function waitForNextTriggerStatus($trigger_id, $next_status, $attempts, $sleep = 2)
    {
        if (!$attempts) {
            return null;
        }

        sleep($sleep);

        $trigger = $this->getTriggerById((int) $trigger_id);

        if (AutopayTrigger::ERROR == $trigger->getStatus() || $next_status == $trigger->getStatus()) {
            return $trigger;
        }

        return $this->waitForNextTriggerStatus($trigger_id, $next_status, $attempts - 1, $sleep);
    }

    /**
     * Возвращает триггер по его идентификатору
     * @param  integer $trigger_id Идентификатор триггера
     * @return Topor\DTO\AutopayTrigger Триггера
     */
    public function getTriggerById($trigger_id)
    {
        $response = $this->get('triggers/' . (int) $trigger_id);
        $trigger = AutopayTrigger::parseFromStdClass($response->data);

        return $trigger;
    }

    /**
     * @param string $base_url
     * @param $logger
     * @return GuzzleHttp\Client
     */
    public function createTransport($base_url)
    {
        $handler = $this->attachMiddleware();

        $transport = new Guzzle([
            'base_uri' => $base_url,
            'auth' => [$this->app_login, $this->app_password],
            'headers' => [
                'User-Agent' => $this->getUserAgent(),
                'Content-type' => 'application/json',
            ],
            'timeout' => 10,
            'connect_timeout' => 5,
            'handler' => $handler,
            'http_errors' => false,
        ]);

        return $transport;
    }

    /**
     * @param $parameters
     * @return array
     */
    protected static function preparePaymentParamsForAutopay($parameters)
    {
        $parameters = array_map(function ($parameter) {
            if ('+7' === substr($parameter, 0, 2)) {
                return substr($parameter, 2);
            }

            return $parameter;
        }, $parameters);

        return $parameters;
    }
}
