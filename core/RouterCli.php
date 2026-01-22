<?php
namespace Core;

/* 路由-命令行 */
class RouterCli extends Base {
  private $name = 'cli';          // 名称
  private $controller = 'main';   // 控制器
  private $method = 'index';      // 方法
  private $params = [];           // 参数

  /* 构造函数 */
  public function __construct(array $argv) {
    $this->parseUrl($argv);
  }

  /* 解析URL: /模块/控制器/方法/参数1/参数2... */
  private function parseUrl(array $argv) {
    unset($argv[0]);
    // 控制器
    if(isset($argv[1])) {
      $this->controller = $argv[1];
      unset($argv[1]);
    }
    // 方法
    if(isset($argv[2])) {
      $this->method = $argv[2];
      unset($argv[2]);
    }
    // 参数
    $this->params = !empty($argv)?array_values($argv):[];
  }

  /* 执行 */
  public function run() {
    // 控制器
    $controllerPath = 'App\\Task\\'.$this->controller;
    // 控制器是否存在
    if(!class_exists($controllerPath)) {
      self::Error('[ '.$this->name.' ]控制器不存在 '.$controllerPath);
    }
    // 实例化
    $controller = new $controllerPath();
    // 方法是否存在
    if(!method_exists($controller, $this->method)) {
      self::Error('[ '.$this->name.' ]方法不存在 '.$controllerPath.'::'.$this->method.'()');
    }
    // 调用方法并传递参数
    echo call_user_func_array([$controller, $this->method], $this->params)."\n";
  }

}