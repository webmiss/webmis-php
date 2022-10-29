<?php
namespace Task;

use Library\Redis;
use Service\Logs as LogsService;

/* 日志 */
class Logs extends Base {

  /* 首页 */
  static function Index(){
    while(true){
      $redis = new Redis();
      $data = $redis->BLPop('logs', 10);
      $redis->Close();
      if(empty($data)) continue;
      // 保存
      $msg = $data[1];
      $res = self::logsWrite($msg);
      if(!$res){
        LogsService::File('upload/erp/Logs.json', json_decode($msg, true));
      }
    }
  }

  /* 写入 */
  static private function logsWrite(string $msg){
    // 数据
    $data = json_decode($msg, true);
    self::Print($data);
    return true;
  }

}