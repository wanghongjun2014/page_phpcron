<?php

namespace Phpcron;

use Phpcron\Adapter\Pdo;
use Phpcron\Adapter\Utils;
use Phpcron\Model\Crontab;
use Phpcron\Model\Model;
use Phpcron\Adapter\Proc;

class Phpcron
{

    protected $_is_run = false;

    protected $pdo = null;

    protected $_master_pid = '';

    protected $config = array();

    protected $timer_config = array();

    protected $frequency_config = array();

    protected $code_config = array();

    protected $cron_model = null;

    protected $model = null;

    protected $first_enter = true;  // 这个变量很关键,

    public function __construct(array $options = array())
    {
        $this->config = $options;
        $this->model = new Model();
        $this->cron_model = new Crontab();
        $this->timer_config = $options['timer'];
        $this->code_config = $options['code'];
        $this->frequency_config = $options['frequency'];
        $pdo = new Pdo($options['db']);
        $this->pdo = $pdo->pdo;

    }


    public function run()
    {
       if ($this->_is_run)
       {
           throw new \RuntimeException('Already running');
       }


       $this->_is_run = true;

       Utils::msg('run:', '入口程序开始执行了');

       $this->_set_master_pid();
       $this->_exec_cron_timer();
    }


    private function _exec_cron_timer()
    {
        $timer = $this->timer_config['cron_min_time'];
        \Swoole\Timer::after($timer, function()
        {
            Utils::msg('exec_timer', 'start........', 'normal');
            $this->_exec_cron_timer();
            $this->_exec_cron();
        });

        $this->_exec_cron();
    }


    /*
     * 执行当前时间的所有crontab
     */

    protected function _exec_cron()
    {
        $server_ip = Utils::get_server_ip();
        $current_server = $this->pdo->get('server', '*', array('ip' => $server_ip));
        if (empty($current_server))
        {
            exit(0);
        }
        $current_server_cron = $this->pdo->select('crontab', '*', array('server_id' => $current_server['id']));

        if (!empty($current_server_cron))
        {
            foreach ($current_server_cron as &$cron)
            {
                $cron['args'] = explode(',', $cron['args']);
                if ($cron['server_id'] == $current_server['id'])
                {
                    // 此条cron指定该台server执行
                    $this->_run($cron);
                }
            }
        }

        $this->first_enter = false;

    }


    /**
     * 判断cron是否可以执行
     * @param $cron 对象格式
     */
    protected function _run($cron, $time = null)
    {
        $exec = false;
        $time || $time = time();


        $is_normal_status = true;
        if ($this->cron_model->checkTime($time, $cron['timer']))
        {
            $exec = true;
        }
        else
        {
            if (!$this->is_cron_normal_status($cron))
            {
                $is_normal_status = false;
            }
        }

        // @todo, 非正常状态需要重启
        if (!$is_normal_status) {
            // 非正常退出状态判断是否允许重启
            if (($cron['repeat_num'] != 0))
            {
                $exec = true;
            }
        }

        $exec && $this->_exec($cron);
    }

    /**
     * 执行某条cron
     * @param $cron
     */
    protected function _exec($cron)
    {
        $proc = new Proc();
        $pids = $proc->showPids($cron['exec_file'], $cron['args']);

        if ($this->_get_master_pid() != $cron['master_pid'])
        {
            //表示入口程序已经重启了
            if (count($pids) > 0)
            {
                // 上一次主进程退了, 但是process进程没有退出的情况
            }
        }

        if (is_file($cron['exec_file']))
        {
            $process = new \Swoole\Process(function(\Swoole\Process $swoole_process) use ($cron) {
                $swoole_process->exec($cron['exec_file'], $cron['args']);
            }, true);


            $retry_count = 0;
            while (true)
            {
                $pid = $process->start();
                if ($pid == false)
                {
                    if ($retry_count <= $this->frequency_config['exec_retry_count'])
                    {
                        $retry_count++;
                    }
                    else
                    {
                        break;
                    }
                }
                else
                {
                    break;
                }
            }
            if ($pid == false) {
                return ;
            }
            // 考虑启动失败的情况, 重试,
            usleep(mt_rand(10000, 30000));
            $start_time = Utils::get_milli_second();
            $ppid = $this->_get_master_pid();

            $insert_ret = $this->pdo->insert('process', array(
                'pid' => $process->pid,
                'ppid' => $ppid,
                'start_time' => $start_time,
                'end_time' => 0,
                'over_time' => $start_time + $cron['timeout'] * 1000,
                'status' => $this->code_config['process_running']

            ));

            $redirect = $cron['redirect'];
            // 读子进程(echo)输出内容，写入定向文件
            \Swoole\Event::add($process, function(\Swoole\Process $process) use ($redirect) {
                $content = $process->read(60000);
                if ($redirect) {
                    \Swoole\Async::writeFile($redirect, $content, null, FILE_APPEND);
                }
            });



        }
    }

    private function _set_master_pid()
    {
        $this->_master_pid = posix_getpid();
    }

    private function _get_master_pid()
    {
        return $this->_master_pid;
    }

    /**
     * 该cron是否处在正常状态
     * @param $cron
     */
    protected function is_cron_normal_status($cron)
    {
        return $cron['exit_code'] == 0;
    }

}