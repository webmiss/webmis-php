<?php
namespace Service;

use Library\FileEo;
use Library\Redis;

/* 日志 */
class Logs extends Base {

  /* 文件 */
  static function File(string $file='', string $content=''){
    FileEo::WriterEnd($file, json_encode($content)."\n");
  }

  /* 生产者 */
  static function Log(array $data) {
    $redis = new Redis();
    $redis->RPush('logs', json_encode($data));
    $redis->Close();
  }

}