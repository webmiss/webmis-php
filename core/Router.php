<?php
namespace Core;

/* 路由 */
class Router extends Base {
  private $name = 'app';          // 名称
  private $module = 'Web';        // 控制器
  private $controller = 'Index';  // 控制器
  private $method = 'Index';      // 方法
  private $params = [];           // 参数

  /* 构造函数 */
  public function __construct() {
    $this->parseUrl();
  }

  /* 解析URL: /模块/控制器/方法/参数1/参数2... */
  private function parseUrl() {
    $url = '';
    if(isset($_SERVER['PATH_INFO'])) $url = $_SERVER['PATH_INFO'];
    elseif(isset($_GET['_url'])) {
      $tmp = explode('?', $_GET['_url']);
      $url = $tmp[0];
      // Get参数
      if(count($tmp)===2) {
        $params = [];
        parse_str($tmp[1], $params);
        foreach($params as $k=>$v) $_GET[$k]=$v;
      }
      unset($_GET['_url']);
    }
    if(!$url) return;
    // URL
    $url = trim($url, '/');
    $url = filter_var($url, FILTER_SANITIZE_URL);
    $url = explode('/', $url);
    // 模块
    if(!empty($url[0])) {
      $this->module = str_replace('_', '', ucwords($url[0], '_'));
      unset($url[0]);
    }
    // 控制器
    if(!empty($url[1])) {
      $this->controller = str_replace('_', '', ucwords($url[1], '_'));
      unset($url[1]);
    }
    // 方法
    if (!empty($url[2])) {
      $this->method = str_replace('_', '', ucwords($url[2], '_'));
      unset($url[2]);
    }
    // 参数
    $this->params = !empty($url)?array_values($url):[];
  }

  /* 执行 */
  public function run() {
    // 控制器
    $controllerPath = 'App\\'.$this->module.'\\'.$this->controller;
    // 控制器是否存在
    if(!class_exists($controllerPath)) {
      self::Print('[ '.$this->name.' ]', '控制器不存在 '.$controllerPath);
      exit;
    }
    // 实例化
    $controller = new $controllerPath();
    // 方法是否存在
    if(!method_exists($controller, $this->method)) {
      self::Print('[ '.$this->name.' ]', '方法不存在 '.$controllerPath.'::'.$this->method.'()');
      exit;
    }
    // 允许跨域请求
    header('Access-Control-Allow-Origin: *');                                 // 域名
    header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');  // 方法
    header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description, Authorization');
    if($_SERVER['REQUEST_METHOD']=='OPTIONS') {
      header('Access-Control-Max-Age: 2592000');                              // OPTIONS(缓存30天)
      exit;
    }
    // 调用方法并传递参数
    echo call_user_func_array([$controller, $this->method], $this->params);
  }
}