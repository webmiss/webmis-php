<?php
namespace App\Task;

use Core\Base;

/* Cli */
class Main extends Base {

  /* 首页 */
  static function Index(string $params='', string $params1=''){
    self::Print($params, $params1);
    return 'cli';
  }

}