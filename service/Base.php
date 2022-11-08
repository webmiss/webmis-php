<?php
namespace Service;

class Base {

  static float $t1 = 0;   //开始时间
  static float $t2 = 0;   //结束时间

  /* 返回JSON */
  static function GetJSON(array $data=[]): string {
    return json_encode($data);
  }

  /* Get参数 */
  static function Get(string $name) {
    return isset($_GET[$name])?$_GET[$name]:null;
  }

  /* Post参数 */
  static function Post(string $name) {
    return isset($_POST[$name])?$_POST[$name]:null;
  }

  /* JSON参数 */
  static function Json() {
    $param = file_get_contents('php://input');
    return $param?json_decode($param, true):[];
  }
  static function JsonName(array $param, string $name) {
    return isset($param[$name])?$param[$name]:'';
  }

  /* 输出到控制台 */
  static function Print(...$content): void {
    foreach($content as $val){
      fwrite(STDERR,self::toString($val).' ');
    }
    fwrite(STDERR,PHP_EOL);
  }
  static private function toString($val): string {
    if(gettype($val)=='array') $val = json_encode($val, JSON_UNESCAPED_UNICODE);
    elseif(gettype($val)=='object') $val = json_encode($val, JSON_UNESCAPED_UNICODE);
    else $val = (string)$val;
    return $val;
  }

  /* 异常错误 */
  static function Error($msg) {
    throw new \InvalidArgumentException($msg);
  }

  /* 测试速度 */
  static function MicroBegin(...$content){
    self::$t1 = microtime(true);
    if($content) self::Print(...$content);
  }
  static function MicroEnd(...$content){
    self::$t2 = microtime(true);
    $t = (int)((self::$t2-self::$t1)*100000)/100;
    self::Print('[time]', date('Y/m/d - H:i:s'), '|', $t.'ms', '|', ...$content);
  }
  
}