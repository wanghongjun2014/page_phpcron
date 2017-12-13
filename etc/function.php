<?php


/**
* 获取配置文件信息
* @param $field
* @param null $key
* @return mixed
*/
function getConfig($field, $key = null)
{
    if (empty($field)) {
        return array();
    }
    $config = require dirname(__DIR__) . '/etc/config.php';
    return $key ? $config[$field][$key] : $config[$field];
}