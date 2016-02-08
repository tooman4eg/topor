<?php namespace Topor;

use Topor\Exception as Exception;
use GuzzleHttp\Client as Guzzle;

class Bestmt extends AbstractClient
{
    const ENV_DEV = 'http://bestmt.dev/';
    const ENV_STAGE = 'http://bestmt.nebo15.me/';
    const ENV_PROD = 'https://bestmt.ru/';

    /**
     * @param $base_url
     * @return Guzzle
     */
    function createTransport($base_url)
    {
        $handler = $this->attachMiddleware();

        $transport = new Guzzle([
            'base_uri' => $base_url,
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

    function agent_points($from_lat, $from_lng, $to_lat, $to_lng)
    {
        $url = sprintf('agent_points/%f,%f-%f,%f.json', floatval($from_lat), floatval($from_lng), floatval($to_lat), floatval($to_lng));
        return $this->get($url);
    }

    function agent_point($agent_point_id)
    {
        $url = "agent_points/".$agent_point_id.'.json';
        return $this->get($url);
    }
}
