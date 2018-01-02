<?php

namespace Phpcron\Model;
use Phpcron\Model\Model As Base_Model;

class Crontab
{

    protected $table = 'page_crontab';

    public function __construct()
    {

    }



    /**
     * 检查是否该执行crontab了
     *
     * @param int    $curr_datetime 当前时间
     * @param string $time_str      时间配置
     *
     * @return boolean
     */
    public function checkTime($curr_datetime, $time_str) {
        $time = explode(' ', $time_str);
        if (count($time) != 5) {
            return false;
        }

        $month = date('n', $curr_datetime); // 没有前导0
        $day = date('j', $curr_datetime); // 没有前导0
        $hour = date('G', $curr_datetime);
        $minute = (int)date('i', $curr_datetime);
        $week = date('w', $curr_datetime); // w 0~6, 0:sunday 6:saturday
        return (self::_isAllow($week, $time[4], 7, 0)
            && self::_isAllow($month, $time[3], 12)
            && self::_isAllow($day, $time[2], 31, 1)
            && self::_isAllow($hour, $time[1], 24)
            && self::_isAllow($minute, $time[0], 60));
    }


    /**
     * 检查是否允许执行
     *
     * @param mixed $needle 数值
     * @param mixed $str 要检查的数据
     * @param int $total_counts 单位内最大数
     * @param int $start 单位开始值（默认为0）
     * @return type
     */
    protected static function _isAllow($needle, $str, $total_counts, $start = 0) {
        // 11:27:25
        // 0 15,16, * * *
        if (strpos($str, ',') !== false) {
            $week_array = explode(',', $str);
            if (in_array($needle, $week_array)) {
                return true;
            }
            return false;
        }
        $array = explode('/', $str);
        $end = $start + $total_counts - 1;
        if (isset($array[1])) {
            if ($array[1] > $total_counts) {
                return false;
            }
            $tmps = explode('-', $array[0]);
            if (isset($tmps[1])) {
                if ($tmps[0] < 0 || $end < $tmps[1]) {
                    return false;
                }
                $start = $tmps[0];
                $end = $tmps[1];
            } else {
                if ($tmps[0] != '*') {
                    return false;
                }
            }
            if (0 == (($needle - $start) % $array[1])) {
                return true;
            }
            return false;
        }
        $tmps = explode('-', $array[0]);
        if (isset($tmps[1])) {
            if ($tmps[0] < 0 || $end < $tmps[1]) {
                return false;
            }
            if ($needle >= $tmps[0] && $needle <= $tmps[1]) {
                return true;
            }
            return false;
        } else {
            if ($tmps[0] == '*' || $tmps[0] == $needle) {
                return true;
            }
            return false;
        }
    }

}