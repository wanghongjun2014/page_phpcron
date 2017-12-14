#! /usr/bin/env php

<?php

// ************* 定义一些全局变量start *************
define('DEBUG', true);
// ************* 定义一些全局变量end ***************


require dirname(__DIR__) . '/etc/function.php';
require dirname(__DIR__) . '/vendor/autoload.php';


$config = require dirname(__DIR__) . '/etc/config.php';

$phpcron = new \Phpcron\Phpcron($config);

$phpcron->run();
