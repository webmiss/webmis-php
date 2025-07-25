<?php
namespace Service;

use Config\Socket;
use Library\FileEo;
use Library\Redis;

/* 日志 */
class Logs extends Base {

  /* 文件 */
  static function File(string $file='', string $content=''){
    FileEo::WriterEnd($file, json_encode($content)."\n");
  }

  /* 系统消息 */
  static function Msg(array $data) {
    $redis = new Redis();
    $redis->RPush(Socket::$redis_name, json_encode($data));
    $redis->Close();
  }

}