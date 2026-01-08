<?php
namespace Core;

/* 控制器 */
class Controller extends Base {

  /* 返回JSON */
  static protected function GetJSON(array $data=[]): string {
    // 语言
    $lang = isset($_GET['lang'])&&$_GET['lang']?$_GET['lang']:'en_US';
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
  static protected function GetLang(string $action, ...$argv): string {
    $lang = isset($_GET['lang'])&&$_GET['lang']?$_GET['lang']:'en_US';
    $name = 'Config\\Langs\\'.$lang;
    $class = new $name();
    return $argv?sprintf($class::$$action, ...$argv):$class::$$action;
  }

  /* Get参数 */
  static protected function Get(string $name) {
    return isset($_GET[$name])?$_GET[$name]:null;
  }

  /* Post参数 */
  static protected function Post(string $name) {
    return isset($_POST[$name])?$_POST[$name]:null;
  }

  /* JSON参数 */
  static protected function Json(): array {
    if($_SERVER['REQUEST_METHOD']=='GET') return $_GET;
    if($_POST) return $_POST;
    $param = file_get_contents('php://input');
    $data = $param?@json_decode($param, true):[];
    return $data?:[];
  }
  static protected function JsonName(array $param, string $name) {
    return isset($param[$name])?$param[$name]:'';
  }

  /* 页面跳转 */
  protected function redirect(string $url, int $statusCode = 302) {
    header("Location: {$url}", true, $statusCode);
    exit;
  }

}