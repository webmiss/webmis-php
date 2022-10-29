<?php
namespace Service;

use Config\Env;
use Library\Redis;

use Model\Model;

/* 数据类 */
class Data extends Base {

  const max8bit = 8;      //随机数位数
  const max10bit = 10;    //机器位数
  const max12bit = 12;    //序列数位数

  // 分区时间
  static public $partition = [
    'p2208'=> 1661961600,
    'p2209'=> 1664553600,
    'plast'=> 1664553600,
  ];
  
  /* 薄雾算法 */
  static function Mist(string $redisName) {
    // 自增ID
    $redis = new Redis();
    $autoId = $redis->Incr($redisName);
    $redis->Close();
    // 随机数
    $randA = mt_rand(0, 255);
    $randB = mt_rand(0, 255);
    // 位运算
    $mist = decbin(($autoId << (self::max8bit + self::max8bit)) | ($randA << self::max8bit) | $randB);
    return bindec($mist);
  }

  /* 雪花算法 */
  static function Snowflake() {
    // 时间戳
    $t = floor(microtime(true) * 1000);
    // 随机数
    $rand = mt_rand(0, 4095);
    // 位运算
    $mist = decbin(($t << (self::max10bit + self::max12bit)) | (Env::$machine_id << self::max12bit) | $rand);
    return bindec($mist);
  }

  /* 图片地址 */
  static function Img(string $img): string {
    return $img?Env::$base_url.$img:'';
  }

  /*
  * 分区-获取ID
  * $p2209 = Data::PartitionID('2022-10-01 00:00:00', 'logs')
  */
  static function PartitionID(string $date, string $table, string $column='ctime'){
    $time = strtotime($date);
    $m = new Model();
    $m->Table($table);
    $m->Columns('id', $column);
    $m->Where($column.' < ?', $time);
    $m->Order($column.' DESC, id DESC');
    $one = $m->FindFirst();
    $one['date'] = $date;
    $one['time'] = $time;
    return $one;
  }

  /* 分区-获取名称 */
  static function PartitionName(int $stime, int $etime){
    $p1 = self::__getPartitionTime($stime);
    $p2 = self::__getPartitionTime($etime);
    $arr = array_keys(self::$partition);
    $p1 = array_search($p1, $arr);
    $p2 = array_search($p2, $arr);
    $len = $p2-$p1+1;
    return implode(',', array_slice($arr, $p1, $len));
  }
  private static function __getPartitionTime(int $time){
    foreach(self::$partition as $name=>$t){
      if($time<$t) return $name;
    }
    return array_key_last(self::$partition);
  }

  /* 分区-SKU定位 */
  static function PartitionSku(string $sku_id){
    $sku = substr($sku_id, 0, 4);
    $now = substr(date('Ym'), 2, 4);
    $last = substr(date('Ym', strtotime('-1 month')), 2, 4);
    if(isset(self::$partition['p'.$sku])){
      return 'p'.$sku;
    }elseif($sku==$now || $sku==$last){
      return array_key_last(self::$partition);
    }else{
      return implode(',', array_keys(self::$partition));
    }
  }

}