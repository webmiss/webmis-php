<?php
namespace App\Service;

use COre\Redis;
use App\Librarys\FileEo;

/* 日志 */
class Logs {

  /* 文件 */
  static function File(string $file='', $content=''){
    FileEo::WriterEnd($file, json_encode($content)."\n");
  }

  /* 商品-日志 */
  static function Goods(array $data) {
    $data['pdate'] = date('Y-m-d');
    $redis = new Redis();
    $redis->RPush('logs_goods', json_encode($data));
    $redis->Close();
  }

}