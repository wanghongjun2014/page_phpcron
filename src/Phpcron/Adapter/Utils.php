<?php

namespace Phpcron\Adapter;

class Utils
{
    const COLOR_BLACK = '30'; // 黑色
    const COLOR_RED = '31';  // 红
    const COLOR_GREE = '32'; // 绿
    const COLOR_YELLOW = '33';  // 黄
    const COLOR_BLUE = '34';  // 蓝
    const COLOR_PURPLE = '35';  // 紫
    const COLOR_SBLUE = '36'; // 深绿
    const COLOR_WHITE = '37'; // 白色

    protected static $color_to_type = array
    (
        'normal' => self::COLOR_SBLUE,
        'warning' => self::COLOR_YELLOW,
        'error' => self::COLOR_RED
    );

    protected static $log = '/tmp/b.log';

    public static function msg($title, $msg, $type = 'normal', $write_log = true) {
        $result = '';
        $color = self::$color_to_type[$type];
        if($color) {
            $result .= "\033[{$color}m";
        }

        $result .= '[' . $title . '] ';

        if($color) {
            $result .= "\033[0m";
        }

        $result .= $msg;
        echo $result . ' ,时间为' . date('Y-m-d H:i:s') . "\n";

        if ($write_log) {
            error_log($result . ' ,时间为' . date('Y-m-d H:i:s') . "\n", 3, self::$log);
        }
    }


    /**
     * @return string
     * swoole获取内网ip
     */
    public static function get_server_ip()
    {
        $ips =  array_values(\swoole_get_local_ip());
        if (isset($ips[0])) {
            return $ips[0];
        }
        return '127.0.0.1';
    }

    /**
     * @param $data
     * @param $filed
     * @return array
     * 取的数组中的某个字段的值
     */
    public static function filter_by_field($data, $filed)
    {
        $ret = array();
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                isset($value[$filed]) && $ret[] = $value[$filed];
            }
        }
        return $ret;
    }


    /**
     * @return float
     * 获取毫秒数
     */
    public static function get_milli_second()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }



}