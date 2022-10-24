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

  /* 分区-获取ID */
  static function PartitionID(string $date, string $table, string $column='utime'){
    $utime = strtotime($date);
    $m = new Model();
    $m->Table($table);
    $m->Columns('id', $column);
    $m->Where($column.' < ?', $utime);
    $m->Order($column.' DESC, id DESC');
    $one = $m->FindFirst();
    $one['date'] = $date;
    return $one;
  }

  /* 分区-获取名称 */
  static function PartitionName(int $stime, int $etime){
    $all = ['p2208', 'p2209', 'plast'];
    $p1 = array_search(self::__getPartitionTime($stime), $all);
    $p2 = array_search(self::__getPartitionTime($etime), $all);
    $len = $p2-$p1+1;
    return implode(',', array_slice($all, $p1, $len));
  }
  private static function __getPartitionTime(int $t){
    $name = '';
    switch(true){
      case $t<1661961600 : $name='p2208'; break;
      case $t>=1661961600 && $t<1664553600 : $name='p2209'; break;
      case $t>=1664553600 : $name='plast'; break;
    }
    return $name;
  }

}