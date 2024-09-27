<?php
namespace Service;

class Base {

  static float $t1 = 0;   //开始时间
  static float $t2 = 0;   //结束时间

  /* 返回JSON */
  static function GetJSON(array $data=[]): string {
    // 语言
    $lang = isset($_GET['lang'])&&$_GET['lang']?$_GET['lang']:'';
    if($lang && isset($data['code']) && !isset($data['msg'])) {
      $name = 'Config\\Langs\\'.$lang;
      $class = new $name();
      $action = 'code_'.$data['code'];
      $data['msg'] = $class::$$action;
    }
    header('Content-type: application/json; charset=utf-8');
    return json_encode($data);
  }

  /* 获取语言 */
  static function GetLang(string $action, ...$argv): string {
    $lang = isset($_GET['lang'])&&$_GET['lang']?$_GET['lang']:'en_US';
    $name = 'Config\\Langs\\'.$lang;
    $class = new $name();
    return $argv?sprintf($class::$$action, ...$argv):$class::$$action;
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
  static function Json(): array {
    if($_SERVER['REQUEST_METHOD']=='GET') return $_GET;
    if($_POST) return $_POST;
    $param = file_get_contents('php://input');
    $data = $param?@json_decode($param, true):[];
    return $data?:[];
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