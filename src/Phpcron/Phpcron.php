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

    protected $process_info = array();

    protected $timeout_info = array();

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

       $this->_exec_cron();

       $this->_exec_cron_timer();

       $this->_register_signal();
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
            //表示入口程序已经重启了, 这地方逻辑需要重新评估
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
                exit();
            }
            $start_time = Utils::get_milli_second();
            $ppid = $this->_get_master_pid();
            $over_time = !empty($cron['timeout']) ? $start_time + $cron['timeout'] * 1000 : 0;
            $insert_ret = $this->pdo->insert('process', array(
                'cron_id' => $cron['id'],
                'pid' => $process->pid,
                'ppid' => $ppid,
                'start_time' => $start_time,
                'end_time' => 0,
                'over_time' => $over_time,
                'status' => $this->code_config['process_running']

            ));

            $this->process_info[$pid] = array(
                'id' => $cron['id'],
                'name' => $cron['name'],
                'server_id' => $cron['server_id'],
                'start_time' => $start_time,
                'process' => $process
            );

            if (!empty($cron['timeout']))
            {
                $this->timeout_info[$pid] = array(
                    'id' => $cron['id'],
                    'expire_time' => $cron['timeout'] * 1000 + $start_time,
                    'timeout_kill_type' => $cron['timeout_kill_type']
                );
            }

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


    /**
     * 注册进程信号事件
     */
    protected function _register_signal()
    {

        // @todo, 当父进程意外退出之后, 由supervisor重启之后就失效了

        \Swoole\Process::signal(SIGCHLD, function($sig) {
            //必须为false，非阻塞模式
            while(($ret = \Swoole\Process::wait(false)) == true) {
                // $ret格式说明, code-状态退出码(比如cron里的php发生fatal error的时候code值为非0), signal-信号量
                usleep(mt_rand(10000, 30000));
                $end_time = Utils::get_milli_second();
                $cron_id = $this->process_info[$ret['pid']]['id'];
                // 数据表中不关心到底是因为php的fatal error退出的还是因为kill 进程导致进程退出的, 只关心进程是否已经退出了
                $exit_code = $ret['code'] != 0 ? $ret['code'] : ($ret['signal'] != 0 ? $ret['signal'] : 0);
                $status = !empty($exit_code) ? $this->code_config['process_exception_end'] : $this->code_config['process_normal_end'];
                echo '子进程结束了';
                echo PHP_EOL;
                print_r($ret);
                $this->pdo->update('process', ['exit_code' => $exit_code, 'status' => $status, 'end_time' => $end_time], ['pid' => $ret['pid']]);
                print_r($this->pdo->last());
                //$this->clear_process_info($ret['pid']);
                //$this->clear_timeout_info($ret['pid']);
            }
        });

        // 这两个是检测主进程信号的, 不是子进程
        \Swoole\Process::signal(SIGINT, [__CLASS__, 'register_die']);
        \Swoole\Process::signal(SIGTERM, [__CLASS__, 'register_die']);
    }

    /**
     * 子进程结束后, 清理进程信息
     * @param $cron
     */
    protected function clear_process_info($pid)
    {
        unset($this->process_info[$pid]);
    }

    /**
     * 子进程结束后, 清理进程超时信息
     * @param $cron
     */
    protected function clear_timeout_info($pid)
    {
        if (isset($this->timeout_info[$pid])) {
            unset($this->timeout_info[$pid]);
        }
    }


    /**
     * 注册死亡信号
     * @param $signal
     */
    public static function register_die($signal)
    {
        Utils::msg('入口程序退出了', '信号为' . $signal, 'error');

        // 入口主进程程序挂了, 逻辑处理部分
        exit(0);
    }





}