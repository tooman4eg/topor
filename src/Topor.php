<?php namespace Topor;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Topor
{
    protected $config;
    protected $logs_dir;
    protected $mserver;
    protected $mbank;
    protected $bestmt;
    protected $services_storage;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->logs_dir = $config['logs_dir'];
    }

    /**
     * @return Mserver
     */
    public function mserver()
    {
        $config = $this->config['mserver'];
        list($login, $password) = $config['credentials'];
        $client = new Mserver($config['env'], $this->createLogger('mserver'));
        $client->setAppAuth($login, $password);
        return $client;
    }

    /**
     * @return Mbank
     */
    public function mbank()
    {
        $config = $this->config['mbank'];
//        list($login, $password) = $config['credentials'];
        $client = new Mbank($config['env'], $this->createLogger('mbank'));
        // $client->setUserAuth($login, $password);
        return $client;
    }

    /**
     * @return Bestmt
     */
    public function bestmt()
    {
        $config = $this->config['bestmt'];
        $client = new Bestmt($config['env'], $this->createLogger('bestmt'));
        return $client;
    }

    /**
     * @return Best
     */
    public function best()
    {
        $config = $this->config['best'];
        $client = new Best($config['env'], $this->createLogger('best'));
        $client->setCredentials(
            $config['partner_id'],
            $config['member_id'],
            $config['credentials'][0],
            $config['credentials'][1],
            isset($config['system_name']) ? $config['system_name'] : null
        );
        return $client;
    }

    public function autopay()
    {
        $config = $this->config['autopay'];
        list($login, $password) = $config['credentials'];
        $client = new Autopay($config['env'], $this->createLogger('autopay'));
        $client->setAppAuth($login, $password);
        return $client;
    }

    /**
     * @return ServicesStorage
     */
    public function services()
    {
        $class = $this->config['services']['storage']['class'];
        return new $class($this->config['services']);
    }

    public function createLogger($name)
    {
        $log_file = $this->logs_dir . '/' . $name . '.log';
        $writer = new StreamHandler($log_file);
        $writer->setFormatter(new LineFormatter(null, null, true));
        $logger = new Logger($name);
        $logger->pushHandler($writer);
        return $logger;
    }
}
