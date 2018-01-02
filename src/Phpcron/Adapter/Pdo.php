<?php

namespace Phpcron\Adapter;
use Phpcron\Model\Model;


class Pdo
{
    public $pdo = null;
    public $options = array();

    public function __construct(array $options = array())
    {
        $this->options = $options;
        $this->connect();
    }

    protected function connect()
    {

        $param = array(
            'database_type' => $this->options['engine'],
            'database_name' => $this->options['database'],
            'server' => $this->options['host'],
            'username' => $this->options['username'],
            'password' => $this->options['password'],

            // [optional]
            'charset' => 'utf8',
            'port' => $this->options['port'],

            // [optional] Table prefix
            'prefix' => 'page_',

            // [optional] Enable logging (Logging is disabled by default for better performance)
            'logging' => true,

        );
        $pdo = new Model($param);

        $this->pdo = $pdo;
    }

    public function __destruct()
    {
        $this->pdo = null;
    }
}