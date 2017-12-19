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
        'process_normal_end' => 2, // 正常由主(父)进程回收的结束子进程
        'process_exception_end' => 3 // 主进程异常退出后, 重启的主进程检测该进程已经不执行的情况下
    ),

    'exit_signal' => array(
        'exit_signal_101' => 101,   // 哥们实在想不出该叫啥了, 先用数字表示吧, 脑仁疼
    ),

    // 各种时间间隔的配置
    'timer' => array(
        'cron_min_time' => 60000,
    ),

    // 各种次数的配置
    'frequency' => array(
        'exec_retry_count' => 3,
    ),

    'log' => array(
        'path' => '/tmp/page_phpcron',
        'type' => 'file'
    ),

);

