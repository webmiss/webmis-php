<?php
namespace Service;

use Config\Env;
use Library\Redis;
use Library\Upload;
use Library\Aliyun\Oss;

use Model\Model;
use Model\UserInfo;

/* 数据类 */
class Data extends Base {

  const max8bit = 8;      //随机数位数
  const max10bit = 10;    //机器位数
  const max12bit = 12;    //序列数位数

  // 分区时间
  static public $partition = [
    'p2501'=> 1738339200,
    'p2502'=> 1740758400,
    'p2503'=> 1743436800,
    'p2504'=> 1746028800,
    'p2505'=> 1748707200,
    'p2506'=> 1751299200,
    'p2507'=> 1753977600,
    'p2508'=> 1756656000,
    'p2509'=> 1759248000,
    'p2510'=> 1761926400,
    'p2511'=> 1764518400,
    'p2512'=> 1767196800,
    'plast'=> 1767196800,
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
  static function Img($img, bool $isTmp=true): string {
    if(!$img) return '';
    return $isTmp?Env::$img_url.$img:Env::$img_url.$img.'?'.date('YmdHis');
  }
  /* 图片地址-商品 */
  static function ImgGoods(string $sku_id, bool $isTmp=true): string {
    return $sku_id?self::Img('img/sku/'.$sku_id.'.jpg', $isTmp):'';
  }

  /* 图片信息-Base64 */
  static function ImgBase64Info(string $base64): array {
    // 类型
    $extAll = [
      'data:image/jpeg;base64' => 'jpg',
      'data:image/png;base64' => 'png',
      'data:image/gif;base64' => 'gif',
    ];
    $ct = explode(',', $base64);
    $ext = $extAll[$ct[0]];
    if(!$ext) return [];
    // 返回
    return [$ext, $ct[1], $ct[0]];
  }

  /* 用户头像 */
  static function UserImg(string $token, string $base64, string $storage='local'): string {
    // 限制格式
    list($ext, $ct) = self::ImgBase64Info($base64);
    if(!in_array($ext, ['jpg', 'png'])) return self::GetJSON(['code'=>4000, 'msg'=>'只能上传JPG、PNG格式图片!']);
    // 文件
    $admin = AdminToken::Token($token);
    $file = 'user/img/'.$admin->uid.'.'.$ext;
    // OSS
    if($storage=='oss'){
      $res = Oss::PutObject($file, $ct);
      if(!$res) return '';
    }else{
      $file = 'upload/'.$file;
      $res = Upload::Base64($file, $ct);
      if(!$res) return '';
    }
    // 保存图片
    $m = new UserInfo();
    $m->Set(['img'=>$file.'?'.time()]);
    $m->Where('uid=?', $admin->uid);
    return $m->Update()?$file:'';
  }

  /*
  * 分区-获取ID
  * $p2209 = Data::PartitionID('2022-10-01 00:00:00', 'logs')
  */
  static function PartitionID(string $date, string $table, string $column='ctime'){
    $t = strtotime($date);
    $m = new Model();
    $m->Table($table);
    $m->Columns('id', $column);
    $m->Where($column.' < ?', $t);
    $m->Order($column.' DESC, id DESC');
    $one = $m->FindFirst();
    $one['date'] = $date;
    $one['time'] = $t;
    $one['table'] = $table;
    return $one;
  }

  /*
  * 分区-获取名称
  * $left: 左偏移量
  * $right: 右偏移量
  */
  static function PartitionName(int $stime, int $etime, int $left=0, int $right=0){
    $p1 = self::__getPartitionTime($stime);
    $p2 = self::__getPartitionTime($etime);
    $arr = [];
    $start = false;
    foreach(self::$partition as $k=>$v){
      if($k==$p1) $start=true;
      if($start) $arr[] = $k;
      if($k==$p2) break;
    }
    $pname = implode(',', $arr);
    if($left==0 && $right==0) return $pname;
    // 偏移量
    $n = count($arr);
    $keys = array_keys(self::$partition);
    $index = array_search($arr[0], $keys);
    $now = array_slice($keys, $index+$left, -$left+$n+$right);
    return implode(',', $now);
  }
  // 按时间获取名称
  private static function __getPartitionTime(int $time){
    $name = '';
    foreach(self::$partition as $k=>$v){
      if($time<$v) return $k;
      $name = $k;
    }
    return $name;
  }

  /* 分区-SKU定位 */
  static function PartitionSku(string $sku_id, array $limt=[]): string {
    $pname = '';
    // 是否日期
    $day = self::PartitionSkuData($sku_id);
    if($day) $pname = 'p'.substr($day, 0, 4);
    if(!$pname) return $pname;
    // 分区
    $keys = array_keys(self::$partition);
    if(!in_array($pname, $keys)) $pname = 'plast';
    // 容差
    if(!$limt) return $pname;
    $index = array_search($pname, $keys);
    $partition = array_slice($keys, $index+$limt[0], $limt[1]);
    return implode(',', $partition);
  }

  /* 分区-SKU范围 */
  static function PartitionSkuLimit(array $sku): string {
    $pname = $tmp = $arr = [];
    foreach($sku as $sku_id) $tmp[substr($sku_id, 0, 6)]=0;
    $tmp = array_keys($tmp);
    foreach($tmp as $sku_id) {
      $d = self::PartitionSkuData($sku_id);
      if($d) $arr['p'.substr($d, 0, 4)] = '';
    }
    $arr = array_keys($arr);
    if(!$arr) return '';
    // 排序
    sort($arr);
    // 分区名称
    $keys = array_keys(self::$partition);
    $start = false;
    foreach($keys as $name) {
      if($name==$arr[0]) $start=true;
      if($start) $pname[] = $name;
      if($name==end($arr)) break;
    }
    return $pname?implode(',', $pname):'plast';
  }

  /* 分区-SKU日期 */
  static function PartitionSkuData(string $sku_id): string {
    // 是否日期
    $str0 = mb_substr($sku_id, 0, 6);
    $str1 = mb_substr($sku_id, 1, 6);
    $str2 = mb_substr($sku_id, 2, 6);
    $str3 = mb_substr($sku_id, 3, 6);
    $str0_1 = mb_substr($sku_id, 0, 2).'0'.mb_substr($sku_id, 2, 3);
    $str1_1 = mb_substr($sku_id, 1, 2).'0'.mb_substr($sku_id, 3, 3);
    $str2_1 = mb_substr($sku_id, 2, 2).'0'.mb_substr($sku_id, 4, 3);
    $str3_1 = mb_substr($sku_id, 3, 2).'0'.mb_substr($sku_id, 5, 3);
    // 日期
    if(self::isDate($str0)) return $str0;
    elseif(self::isDate($str1)) return $str1;
    elseif(self::isDate($str2)) return $str2;
    elseif(self::isDate($str3)) return $str3;
    elseif(self::isDate($str0_1)) return $str0_1;
    elseif(self::isDate($str1_1)) return $str1_1;
    elseif(self::isDate($str2_1)) return $str2_1;
    elseif(self::isDate($str3_1)) return $str3_1;
    return '';
  }
  // 是否日期
  private static function isDate($dateStr): bool {
    $res = substr(date('Ymd', strtotime('20'.$dateStr)), -6);
    return $dateStr==$res;
  }

  /**
  * 获取时段
  * @param string $mode hour/day/month
  */
  static function getTimes(string $stime, string $etime): array {
    $times = [];
    // 小时
    if($stime==$etime){
      // 24小时
      for($i=0; $i<24; $i++) {
        $h = $i<10?'0'.$i:(string)$i;
        $st = strtotime($stime.' '.$h.':00:00');
        $et = strtotime($etime.' '.$h.':59:59');
        $times[$h] = [$st, $et];
      }
      return $times;
    }
    // 天
    $d1 = new \DateTime($stime);
    $d2 = new \DateTime($etime);
    $res = $d1->diff($d2);
    if($res->y==0 && $res->m==0) {
      for($i=$res->days; $i>=0; $i--) {
        $t = strtotime($etime.' -'.$i.' day');
        $y = date('Y', $t);
        $m = date('m', $t);
        $d = date('d', $t);
        $st = strtotime($y.'-'.$m.'-'.$d.' 00:00:00');
        $et = strtotime($y.'-'.$m.'-'.$d.' 23:59:59');
        $times[$m.'/'.$d] = [$st, $et];
      }
      return $times;
    }
    // 月
    if($res->y==0) {
      for($i=$res->m; $i>=0; $i--) {
        $t = strtotime($etime.' -'.$i.' month');
        $y = date('Y', $t);
        $m = date('m', $t);
        $st = strtotime($y.'-'.$m.'-01 00:00:00');
        $time = new \DateTime(date('Y-m-d', $t));
        $time->modify('last day of this month');
        $et = strtotime($y.'-'.$m.'-'.$time->format('j').' 23:59:59');
        $times[$y.'/'.$m] = [$st, $et];
      }
      return $times;
    }
    // 年
    for($i=$res->y; $i>=0; $i--) {
      $d = date('Y', strtotime($etime.' -'.$i.' year'));
      $st = strtotime($d.'-01-01 00:00:00');
      $et = strtotime($d.'-12-31 23:59:59');
      $times[$d] = [$st, $et];
    }
    return $times;
  }

  /* 获取上期日期 */
  static function getOldDay(string $stime, string $etime){
    // 日期
    $d1 = new \DateTime($stime);
    $d2 = new \DateTime($etime);
    $res = $d1->diff($d2);
    $day = $res->days+1;
    // 上期
    $sDay = date('Y-m-d', strtotime('-'.$day.' days', strtotime($stime)));
    $eDay = date('Y-m-d', strtotime('-1 days', strtotime($stime)));
    return [$sDay, $eDay];
  }

}