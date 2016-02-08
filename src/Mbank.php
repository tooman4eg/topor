<?php namespace Topor;

use Rhumsaa\Uuid\Uuid;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client as Guzzle;

use Topor\Exception;

class Mbank extends AbstractClient
{
    const ENV_DEV = 'https://sandbox.wallet.best/v1/';
    const ENV_PROD = 'https://api.wallet.best/v1/';

    function setUserAuth($login, $password)
    {
        $this->transport()->setDefaultOption('auth', [$login, $password]);
    }

    function createAccount($phone, $password)
    {
        try {
            return $this->post('wallet', [
                'phone' => $phone,
                'password' => $password
            ], true);
        } catch (Exception\UnprocessableEntity $event) {
            if ('phone_already_exists' == $event->getResponseContent()->meta->error) {
                throw new Exception\PhoneAlreadyExists(
                    "Phone already exists",
                    $event->getRequest(),
                    $event->getResponse()
                );
            }
        }
        return null;
    }

    function activateAccount($code)
    {
        return $this->post('wallet/activate', [
            'phone' => $this->phone,
            'code' => $code
        ], true);
    }

    function sendPasswordResetCode($phone)
    {
        return $this->post('wallet/send_password_reset_code', [
            'phone' => $phone
        ]);
    }

    function resetPassword($code, $phone, $password)
    {
        return $this->post('wallet/reset_password', [
            'phone' => $phone,
            'code' => $code,
            'password' => $password
        ]);
    }

    function tryLogin($phone, $password)
    {
        try {
            $this->phone = $phone;
            $this->password = $password;
            return $this->get('wallet');
        } catch (ClientException $e) {
            if (401 == $e->getResponse()->getStatusCode()) {
                return null;
            } else {
                throw $e;
            }
        }
    }

    function getPersonalInfo()
    {
        return $this->get('wallet/person');
    }

    function fillPersonalInfo($properties)
    {
        if (!isset($properties['family_name'])) {
            throw new \DomainException('family_name is required');
        }

        if (!isset($properties['given_name'])) {
            throw new \DomainException('given_name is required');
        }

        return $this->post('wallet/person', $properties);
    }

    function servicesGroups($modified_after = null, $skip_empty_groups = true)
    {
        $path = 'services/groups';

        if ($modified_after) {
            $path .= '?If-Modified-Since='.$modified_after;
        }
        $response_data = $this->get($path);

        if ($modified_after && is_null($response_data)) {
            return (object) [
                'meta' => (object) [
                    'code' => 200
                ],
                'data' => []
            ];
        }

        foreach ($response_data->data as $i => $group) {
            if (!property_exists($group, 'icon_url_32x32')) {
                $response_data->data[$i]->icon_url_32x32 = null;
            }
            if ($skip_empty_groups && !$group->services) {
                unset($response_data->data[$i]);
            }
        }

        return $response_data;
    }

    function servicesGroup($id)
    {
        $groups = $this->servicesGroups();
        foreach ($groups->data as $group) {
            if ($group->id == $id) {
                $groups->data = $group;
                if (!property_exists($groups->data, 'icon_url_32x32')) {
                    $groups->data->icon_url_32x32 = null;
                }
                return $groups;
            }
        }

        throw new \Exception('Make empty 200 response');
        return null;
    }

    function account()
    {
        $response_data = $this->get('wallet');
        if ($response_data->meta->code == 200) {
            if (!property_exists($response_data->data, 'name')) {
                $response_data->data->name = null;
            }
        }
        return $response_data;
    }

    function service($id)
    {
        try {
            return $this->get('services/'.$id);
        } catch (Exception\NotFound $e) {
            throw new \Exception('Make empty 200 response');
            return null;
        }
    }

    function services()
    {
        $response_data = $this->get('services');
        return $response_data;
    }

    function suggestMobileService($phone)
    {
        $response_data = $this->get('services/mobile/'.$phone);
        return $response_data;
    }

    function payments($limit = 35, $page = 0, $type = null, $statuses = [])
    {
        $query = [
            'size' => $limit,
            'page' => $page,
            'type' => $type,
            'status' => implode(',', $statuses)
        ];
        $response_data = $this->get('payments', [ 'query' => $query ]);
        return $response_data;
    }

    function payment($id)
    {
        $response_data = $this->get('payments/'.$id);
        return $response_data;
    }

    function createPaymentForService($service_id, $amount, $parameters, $client_payment_id = null)
    {
        $client_payment_id = $client_payment_id ?: (string) Uuid::uuid1();
        $data = [
            'type' => 'out',
            'service' => $service_id,
            'client_payment_id' => $client_payment_id,
            'amount' => $amount,
            'parameters' => $parameters
        ];
        $response_data = $this->post('payments', $data);
        return $response_data;
    }

    function createPaymentToUserByPhone($phone, $amount, $message, $client_payment_id = null)
    {
        $client_payment_id = $client_payment_id ?: (string) Uuid::uuid1();
        $data = [
            'type' => 'p2p',
            'destination' => $phone,
            'client_payment_id' => $client_payment_id,
            'amount' => $amount,
            'message' => $message
        ];
        $response_data = $this->post('payments', $data);
        return $response_data;
    }

    function createPaymentForReplenish($amount, $client_payment_id = null)
    {
        $client_payment_id = $client_payment_id ?: (string) Uuid::uuid1();
        $data = [
            'type' => 'in',
            'client_payment_id' => $client_payment_id,
            'amount' => $amount
        ];
        $response_data = $this->post('payments', $data);
        return $response_data;
    }

    function approvePayment($payment_id)
    {
        $response_data = $this->post('payments/'.$payment_id.'/pay', []);
        return $response_data;
    }

    function createCard()
    {
        $response_data = $this->post('cards', []);
        return $response_data;
    }

    function card($card_id)
    {
        $response_data = $this->get('cards/'.$card_id);
        return $response_data;
    }

    function cards()
    {
        $response_data = $this->get('cards');
        return $response_data;
    }

    function deleteCard($card_id)
    {
        return $this->delete('cards/'.$card_id);
    }

    function createInvoice($payer_phone, $amount, $message)
    {
        return $this->post('invoices', [
            'payer' => $payer_phone,
            'amount' => $amount,
            'message' => $message
        ]);
    }

    function inboundInvoices()
    {
        return $this->get('invoices');
    }

    function outboundInvoices()
    {
        return $this->get('invoices/created');
    }

    function duplicateInvoice($invoice_id)
    {
        return $this->get('invoices/'.$invoice_id.'/duplicate');
    }

    function cancelInvoice($invoice_id)
    {
        return $this->get('invoices/'.$invoice_id.'/cancel');
    }

    function payInvoice($invoice_id)
    {
        return $this->post('invoices/'.$invoice_id.'/pay', []);
    }

    function createTransport($base_url)
    {
        $handler = $this->attachMiddleware();

        $transport = new Guzzle([
            'base_uri' => $base_url,
            // 'auth' => [$this->phone, $this->password],
            'headers' => [
                'User-Agent' => 'client',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
            'connect_timeout' => 5,
            'handler' => $handler,
            'http_errors' => false
        ]);
        return $transport;
    }
}
