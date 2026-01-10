<?php
namespace App\Config;

/* 邮箱 */
class Email {

  /* 配置 */
  static function config(string $name='default'): array {
    $data = [
      'default'=> [
        'host'=> 'smtp.163.com',            // 邮件服务器
        'smtp_auth'=> true,                 // 验证
        'name'=> '',                        // 别名
        'username'=> 'webmis@163.com',      // 账号
        'password'=> '',                    // 授权密码
        'port'=> 465,                       // 端口
        'charset'=> 'UTF-8',                // 编码
      ],
      'other'=> [
        'host'=> 'smtp.163.com',            // 邮件服务器
        'smtp_auth'=> true,                 // 验证
        'name'=> '',                        // 别名
        'username'=> 'webmis@163.com',      // 账号
        'password'=> '',                    // 授权密码
        'port'=> 465,                       // 端口
        'charset'=> 'UTF-8',                // 编码
      ]
    ];
    return $data[$name];
  }

}