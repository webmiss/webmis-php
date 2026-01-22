<?php
namespace Core;

/* 视图 */
class View extends Base {

  static private $name = 'View';  // 名称
  static private $data = [];      // 视图变量

  /* 赋值变量 */
  static function assign(string $key, $value) {
    self::$data[$key] = $value;
  }

  /* 渲染 */
  static function render(string $viewName, string $layout='layout'): string {
    extract(self::$data);
    // 视图文件
    $viewPath = '../app/views/'.$viewName.'.php';
    if(!file_exists($viewPath)) {
      self::Print('[ '.self::$name.' ]', '视图不存在 '.$viewPath);
      return '';
    }
    // 内容
    ob_start();
    require $viewPath;
    $content = ob_get_clean();
    // 模板文件
    $layoutPath = '../app/views/layouts/'.$layout.'.php';
    if(file_exists($layoutPath)) require $layoutPath;
    return $content;
  }

}