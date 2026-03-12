<?php
namespace Core;

/* 控制器 */
class Controller extends Base {

  /* 资源地址 */
  static function BaseUrl($url='', $host='') {
    if($host) return $host.$url;
    $str = isset($_SERVER['HTTPS'])?'https':'http';
    // return 'http://localhost/php/public/'.$url;
    return $str.'://'.$_SERVER['HTTP_HOST'].'/'.$url;
  }

  /* 返回JSON */
  static protected function GetJSON(array $data=[]): string {
    // Json类型
    header('Content-type: application/json; charset=utf-8');
    // 语言
    if(isset($data['code']) && !isset($data['msg'])) {
      $data['msg'] = self::GetLang('code_'.$data['code']);
    }
    // 返回
    return json_encode($data);
  }

  /* 获取语言 */
  static protected function GetLang(string $action, ...$argv): string {
    $lang = isset($_GET['lang'])&&$_GET['lang']?$_GET['lang']:'en_US';
    $path = 'App\\Config\\Langs\\';
    $controller = $path.strtolower($lang);
    if(!class_exists($controller)) $controller = $path.'en_us';
    $class = new $controller();
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