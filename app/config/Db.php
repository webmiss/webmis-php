<?php
namespace App\Config;

/* 数据库 */
class Db {

  /* 配置 */
  static function Config(string $name='default'): array {
    $data = [];
    switch($name) {
      case 'default':
        $data['driver'] = 'mysql';                                 // 类型
        $data['host'] = '127.0.0.1';                               // 主机
        $data['port'] = 3306;                                      // 端口
        $data['user'] = 'webmis';                                  // 账号
        $data['password'] = 'e4b99adec618e653400966be536c45f8';    // 密码
        $data['database'] = 'webmis';                              // 数据库
        $data['charset'] = 'utf8mb4';                              // 编码
        $data['persistent'] = true;                                // 持久链接
        break;
      case 'other':
        $data['driver'] = 'mysql';                                 // 类型
        $data['host'] = '127.0.0.1';                               // 主机
        $data['port'] = 3306;                                      // 端口
        $data['user'] = 'webmis';                                  // 账号
        $data['password'] = '123456';                              // 密码
        $data['database'] = 'webmis';                              // 数据库
        $data['charset'] = 'utf8mb4';                              // 编码
        $data['persistent'] = true;                                // 持久链接
        break;
    }
    return $data;
  }

}