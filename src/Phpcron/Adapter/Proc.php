<?php
/**
 * Created by PhpStorm.
 * User: wanghongjun
 * Date: 2017/11/17
 * Time: 下午2:38
 */

namespace Phpcron\Adapter;

class Proc implements \ArrayAccess {
    /**
     * 参数分隔符
     * @var unknown
     */
    const LIMITER = "\x00";

    /**
     * 进程数据
     *
     * @var array
     */
    protected $_data = array();

    /**
     * 构造方法
     * 获取进程信息
     *
     * @return void
     */
    public function __construct() {
        $dir = new \DirectoryIterator('/proc');
        foreach ($dir as $fileinfo) {
            // 非目录，不操作
            if (!$fileinfo->isDir()) {
                continue;
            }

            // 非数字目录，不操作
            $pid = $fileinfo->getFilename();
            if (!is_numeric($pid)) {
                continue;
            }

            // 进程命令文件
            $cmdline_file = "/proc/{$pid}/cmdline";
            if (!is_file($cmdline_file)) {
                continue;
            }

            // 将命令对应PID关系存起
            $cmdline = trim(file_get_contents($cmdline_file), self::LIMITER);
            $this->_data[$cmdline][] = (int)$pid;
        }
    }

    /**
     * 获取指定进程的PID
     *
     * @param string $exec_file 执行的程序
     * @param array  $args      参数
     *
     * @return int[]
     */
    public function showPids($exec_file, array $args) {
        $cmdline = $exec_file;
        if ($args) {
            $cmdline .= self::LIMITER . implode(self::LIMITER, $args);
        }
        return isset($this->_data[$cmdline]) ? $this->_data[$cmdline] : array();
    }

    /**
     * {@inheritDoc}
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset) {
        return isset($this->_data[$offset]);
    }

    /**
     * {@inheritDoc}
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($offset) {
        return $this->_data[$offset];
    }

    /**
     * {@inheritDoc}
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value) {
        $this->_data[$offset] = $value;
    }

    /**
     * {@inheritDoc}
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset) {
        unset($this->_data[$offset]);
    }
}
