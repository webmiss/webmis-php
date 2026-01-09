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
    if(isset($_SERVER['PATH_INFO'])) {
      // URL
      $url = trim($_SERVER['PATH_INFO'], '/');
      $url = filter_var($url, FILTER_SANITIZE_URL);
      $url = explode('/', $url);
      // 模块
      if(!empty($url[0])) {
        $this->module = ucfirst($url[0]);
        unset($url[0]);
      }
      // 控制器
      if(!empty($url[1])) {
        $this->controller = ucfirst($url[1]);
        unset($url[1]);
      }
      // 方法
      if (!empty($url[2])) {
        $this->method = ucfirst($url[2]);
        unset($url[2]);
      }
      // 参数
      $this->params = !empty($url)?array_values($url):[];
    }
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
    if (!method_exists($controller, $this->method)) {
      self::Print('[ '.$this->name.' ]', '方法不存在 '.$controllerPath.'::'.$this->method.'()');
      exit;
    }
    // 调用方法并传递参数
    echo call_user_func_array([$controller, $this->method], $this->params);
  }
}