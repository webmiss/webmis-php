<?php
namespace App\Service;

use Core\Redis;
use App\Librarys\FileEo;

/* 日志 */
class Logs {

  /* 文件 */
  static function File(string $file, array|string $content=''){
    FileEo::WriterEnd($file, is_array($content)?json_encode($content):$content."\n");
  }

  /* 商品-日志 */
  static function Goods(array $data) {
    $data['pdate'] = date('Y-m-d');
    $redis = new Redis();
    $redis->RPush('logs_goods', json_encode($data));
  }

}