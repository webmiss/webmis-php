<?php
namespace App\Task;

use Core\Base;

/* Cli */
class Main extends Base {

  /* 首页 */
  static function Index(string $params='', string $params1=''){
    self::Print($params, $params1);
    Socket::client('admin', json_encode(['type'=>'', 'title'=>'开发', 'content'=>'测试']));
    return 'PHP Cli';
  }

}