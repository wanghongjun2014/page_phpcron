<?php
namespace Phpcron\Adapter;

use DateTime;
use RuntimeException;


class Logger
{

    const INFO = 'INFO';
    const ERROR = 'ERROR';

    protected $option = array(
        'path' => '/tmp/logger/',
        'format' => 'Y-m-d',
        'ext' => '.log'
    );


   public function __construct($options = array())
   {
       $options = array_merge($this->option, $options);
       $this->option = $options;
   }

   public function info($msg)
   {
        $this->record($msg, self::INFO);
   }

   public function error($msg)
   {
       $this->record($msg, self::ERROR);
   }


   protected function record($msg, $type)
   {
        $this->_format_string($msg, $type);
   }

   protected function _format_string($data, $type)
   {
       if (!is_string($data)) {
           $data = json_encode($data, JSON_UNESCAPED_UNICODE);
       }

       $time = date('Y-m-d H:i:s');
       $string =  '[ ' . $time . ' ] ' . $type .': ' . $data . PHP_EOL;

       if (!is_dir($this->option['path'])) {
           @mkdir(iconv("UTF-8", "GBK", $this->option['path']), 0777, true);
       }

       $format = $this->option['format'];
       error_log($string, 3 , $this->option['path'] . DIRECTORY_SEPARATOR . date($format, time()) . $this->option['ext']);
   }
}
