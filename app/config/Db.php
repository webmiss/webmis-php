<?php
namespace App\Config;

/* 数据库 */
class Db {

  /* 配置 */
  static function config(string $name='default'): array {
    $db = [
      'default'=> [
        'driver'=> 'mysql',                                 // 类型
        'host'=> '127.0.0.1',                               // 主机
        'port'=> 3306,                                      // 端口
        'username'=> 'webmis',                              // 账号
        'password'=> 'e4b99adec618e653400966be536c45f8',    // 密码
        'dbname'=> 'webmis',                                // 数据库名
        'charset'=> 'utf8mb4',                              // 编码
        'persistent'=> true,                                // 持久链接
      ],
      'other'=> [
        'driver'=> 'mysql',                                 // 类型
        'host'=> '127.0.0.1',                               // 主机
        'port'=> 3306,                                      // 端口
        'username'=> 'webmis',                              // 账号
        'password'=> 'e4b99adec618e653400966be536c45f8',    // 密码
        'dbname'=> 'webmis',                                // 数据库名
        'charset'=> 'utf8mb4',                              // 编码
        'persistent'=> true,                                // 持久链接
      ]
    ];
    return $db[$name];
  }

}