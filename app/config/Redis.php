<?php
namespace App\Config;

/* 缓存数据库 */
class Redis {

  /* 配置 */
  static function config(string $name='default'): array {
    $data = [];
    switch($name) {
      case 'default':
        $data['host'] = '127.0.0.1';                               // 主机
        $data['port'] = 6379;                                      // 端口
        $data['password'] = '';                                    // 密码
        $data['db'] = 0;                                           // 硬盘
        $data['socket_timeout'] = 3;                               // 连接超时
        break;
      case 'other':
        $data['host'] = '127.0.0.1';                               // 主机
        $data['port'] = 6379;                                      // 端口
        $data['password'] = 'e4b99adec618e653400966be536c45f8';    // 密码
        $data['db'] = 0;                                           // 硬盘
        $data['socket_timeout'] = 3;                               // 连接超时
        break;
    }
    return $data;
  }

}