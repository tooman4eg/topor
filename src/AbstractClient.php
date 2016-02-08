<?php
namespace Topor;

use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter as Formatter;
use GuzzleHttp\Middleware;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Topor\Exception\RemoteServerInternalError;

abstract class AbstractClient
{
    const ANONYMOUS = 'anonymous';
    const IDENTIFIED = 'identified';
    const PERSONIFIED = 'personified';

    const PERSON_EMPTY = 'no_data';
    const PERSON_ENTERED = 'data_entered';
    const PERSON_VERIFIED = 'data_verified';

    const PAY_REFILL = 'in';
    const PAY_SERVICE = 'out';
    const PAY_CARD_TO_SERVICE = 'inout';
    const PAY_TRANSFER = 'p2p';

    const CARD_CREATED = 'created';
    const CARD_PENDING = 'pending';
    const CARD_ACTIVE = 'active';
    const CARD_FAILED = 'failed';
    const CARD_USED = 'used';

    const CREATING = 'creating';
    const CREATED = 'created';
    const CANCELED = 'canceled';
    const PROCESSING = 'processing';
    const COMPLETED = 'completed';
    const DECLINED = 'declined';

    const UPDATE = 'update';
    const PAY = 'pay';

    /**
     * @var \GuzzleHttp\Client
     */
    protected $transport;
    /**
     * @var \GuzzleHttp\Pool
     */
    protected $pool;
    /**
     * @var \Monolog\Logger
     */
    protected $logger;
    /**
     * @var \GuzzleHttp\MessageFormatter
     */
    protected $log_formatter;

    public function __construct(
        $base_url,
        Logger $logger = null,
        $log_formatter =
        Formatter::DEBUG
    ) {
        $this->base_url = $base_url;
        $this->logger = $logger;
        $this->log_formatter = $log_formatter;
    }

    /**
     * @param $base_url
     * @return \GuzzleHttp\Client
     */
    abstract public function createTransport($base_url);

    /**
     * @return \GuzzleHttp\Client
     */
    public function transport()
    {
        if (!$this->transport) {
            $this->transport = $this->createTransport($this->base_url);
        }
        return $this->transport;
    }

    /**
     * Добавляет необходимую логику для логгирования и
     * перехвата ошибок необходимо вызывать перед
     * созданием самого \GuzzleHttp\Client
     *
     * @param \GuzzleHttp\HandlerStack $handler
     * @return \GuzzleHttp\HandlerStack
     */
    public function attachMiddleware(HandlerStack $handler = null)
    {
        if (is_null($handler)) {
            $handler = HandlerStack::create();
        }

        //Добавляем логгирование
        $handler->push(Middleware::log($this->logger, new Formatter($this->log_formatter)));

        //Добавляем перехватывание ошибок
        $handler->push($this->errorsMiddleware());

        return $handler;
    }

    /**
     * Отрабатывает HTTP-ошибки 4хх-5хх и неудачные попытки соединения
     *
     * @return callable Returns a function that accepts the next handler.
     */
    public function errorsMiddleware()
    {
        return function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $handler) {
                        if (!$response) {
                            $msg = "Empty response from or timeout succeeded";
                            $this->logger->error($msg, ['request' => $request]);
                            throw new Exception\NetworkError(
                                "Empty response from or timeout succeeded",
                                $request,
                                $response
                            );
                        }
                        $code = $response->getStatusCode();
                        if ($code < 400) {
                            return $response;
                        }

                        switch ($code) {
                            case 400:
                                return static::process400($request, $response);
                            case 401:
                                return static::process401($request, $response);
                            case 404:
                                return static::process404($request, $response);
                            case 422:
                                return static::process422($request, $response);
                            case 500:
                                return static::process500($request, $response);
                            default:
                                return static::processOtherErrors($request, $response);
                        }
                    }
                )->otherwise(function (\Exception $e) use ($request) {
                    if ($e instanceof GuzzleConnectException) {
                        $handler_context = $e->getHandlerContext();
                        throw new GuzzleConnectException(
                            $handler_context['error'] . ', url: ' . $handler_context['url'],
                            $request,
                            null,
                            $handler_context
                        );
                    } else {
                        throw $e;
                    }
                });
            };
        };
    }

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return mixed
     */
    protected static function process400($request, $response)
    {
        throw new Exception\BadRequest(
            'Check request params',
            $request,
            $response
        );
    }

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws \Topor\Exception\Unauthorized
     * @throws \Topor\Exception\BadCredentials
     * @return mixed
     */
    protected static function process401($request, $response)
    {
        list($phone, $password) = array('', ''); //$request->getConfig()->get('auth');
        if (!$phone || !$password) {
            throw new Exception\Unauthorized(
                'Login and password is required',
                $request,
                $response
            );
        } else {
            throw new Exception\BadCredentials(
                'Wrong login or password',
                $request,
                $response
            );
        }
    }

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws \Topor\Exception\NotFound
     * @return mixed
     */
    protected static function process404($request, $response)
    {
        throw new Exception\NotFound(
            'Not found',
            $request,
            $response
        );
    }

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws \Topor\Exception\UnprocessableEntity
     * @return mixed
     */
    protected static function process422($request, $response)
    {
        $error_message = json_decode((string) $response->getBody())->meta->error;
        throw new Exception\UnprocessableEntity(
            'Unprocessable entity: ' . $error_message,
            $request,
            $response
        );
    }

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws \Topor\Exception\RemoteServerInternalError
     * @return mixed
     */
    protected static function process500($request, $response)
    {
        throw new RemoteServerInternalError(
            'Internal remote server error',
            $request,
            $response
        );
    }

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws \Topor\Exception\ResponseException
     * @return mixed
     */
    protected static function processOtherErrors($request, $response)
    {
        $resp = $response->getBody()->__toString();
        $resp_obj = json_decode($resp);

        if (!is_object($resp_obj)) {
            throw new Exception\ResponseException(
                "Response not a json: '$resp'",
                $request,
                $response
            );
        }

        $meta = property_exists($resp_obj, 'meta') ? $resp_obj->meta : new \stdClass();

        if (!property_exists($meta, 'code')) {
            $meta->code = '<empty error code>';
        }

        if (!property_exists($meta, 'error')) {
            $meta->error = '<empty error message>';
        }

        throw new Exception\ResponseException(
            "[$meta->code] $meta->error",
            $request,
            $response
        );
    }

    public function get($path, $options = [])
    {
        $response = $this->transport()->get($path, $options);
        $response_data = json_decode((string) $response->getBody());
        return $response_data;
    }

    public function post($path, $data = [])
    {
        $options = [
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ];

        $response = $this->transport()->post($path, $options);
        $response_data = json_decode((string) $response->getBody());

        return $response_data;
    }

    public function delete($path)
    {
        $response = $this->transport()->delete($path);
        return json_decode((string) $response->getBody());
    }
}
