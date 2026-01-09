<?php
namespace App\Config;

/* Redis */
class Redis {

  /* 配置 */
  static function config(string $name='default'): array {
    $db = [
      'default'=> [
        'host'=> '127.0.0.1',       // 主机
        'port'=> 6379,              // 端口
        'password'=> 'e4b99adec618e653400966be536c45f8',
        'db'=> 0,                   // 硬盘
      ],
      'other'=> [
        'host'=> '127.0.0.1',       // 主机
        'port'=> 6379,              // 端口
        'password'=> 'e4b99adec618e653400966be536c45f8',
        'db'=> 0,                   // 硬盘
      ]
    ];
    return $db[$name];
  }

}