<?php
namespace Task;

use Library\Redis;
use Model\ErpGoodsLogs;

/* 日志 */
class Logs extends Base {

  /* 商品-日志 */
  static function Goods(){
    $n=1000; $t=10; $key='logs_goods';
    while(true){
      $redis = new Redis();
      $data = $redis->LRange($key, 0, $n);
      if(!$data){
        $redis->Close();
        sleep($t);
        continue;
      }
      // 数据
      $msg = [];
      foreach($data as $v){
        $res = $redis->LPop($key);
        $tmp = $res?json_decode($res, true):[];
        if(!isset($tmp['ctime']) || !isset($tmp['operator_id']) || !isset($tmp['operator_name']) || !isset($tmp['sku_id']) || !isset($tmp['content'])) continue;
        $msg[] = $tmp;
      }
      $redis->Close();
      self::goodsWrite($msg);
    }
  }
  /* 商品-写入日志 */
  static private function goodsWrite(array $data){
    $m = new ErpGoodsLogs();
    $m->ValuesAll($data);
    $m->Insert();
  }

}