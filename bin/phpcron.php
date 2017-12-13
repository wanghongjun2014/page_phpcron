#! /usr/bin/env php

<?php


require dirname(__DIR__) . '/etc/function.php';
require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/etc/config.php';

$phpcron = new \Phpcron\Phpcron($config);

$phpcron->run();
