<?php

return array(
    // db配置
    'db' => array(
        'engine' => 'mysql',
        'host' => 'rm-2zey9z5087ifc26hlo.mysql.rds.aliyuncs.com',
        'port' => 3306,
        'username' => 'wanghongjun',
        'password' => 'Wanghongjun2017',
        'database' => 'wealth-market',
        'options' => array()
    ),

    // 各种状态码的配置
    'code' => array(
        'process_running' => 1,
        'process_normal_end' => 2,
        'process_exception_end' => 3
    ),

    // 各种时间间隔的配置
    'timer' => array(
        'cron_min_time' => 60000,
    ),

    // 各种次数的配置
    'frequency' => array(
        'exec_retry_count' => 3,
    )

);

