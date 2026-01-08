<?php
namespace Core;

/* 基础 */
class Base {

  static float $t1 = 0;   //开始时间
  static float $t2 = 0;   //结束时间

  /* 输出到控制台 */
  static protected function Print(...$content): void {
    foreach($content as $val){
      fwrite(STDERR, self::toString($val).' ');
    }
    fwrite(STDERR, PHP_EOL);
  }
  static private function toString($val): string {
    if(gettype($val)=='array') $val = json_encode($val, JSON_UNESCAPED_UNICODE);
    elseif(gettype($val)=='object') $val = json_encode($val, JSON_UNESCAPED_UNICODE);
    else $val = (string)$val;
    return $val;
  }

  /* 异常错误 */
  static protected function Error($msg) {
    throw new \InvalidArgumentException($msg);
  }

  /* 测试速度 */
  static protected function MicroBegin(...$content){
    self::$t1 = microtime(true);
    if($content) self::Print(...$content);
  }
  static protected function MicroEnd(...$content){
    self::$t2 = microtime(true);
    $t = (int)((self::$t2-self::$t1)*100000)/100;
    self::Print('[ time ]', date('Y/m/d - H:i:s'), '|', $t.'ms', '|', ...$content);
  }

}