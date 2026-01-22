<?php
namespace App\Task;

use Core\Base;

/* Cli */
class main extends Base {

  /* 首页 */
  static function index(string $params='', string $params1=''){
    self::Print($params, $params1);
    Socket::client('admin', json_encode(['type'=>'', 'title'=>'开发', 'content'=>'测试']));
    return 'PHP Cli';
  }

}