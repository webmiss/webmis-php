<?php
namespace Config;

/* 邮箱配置 */
class Email {

  /* 默认 */
  static function Default(): array {
    return [
      'host'=> 'smtp.163.com',            // 邮件服务器
      'smtp_auth'=> true,                 // 验证
      'name'=> 'webmis',                  // 别名
      'username'=> 'webmisphp@163.com',     // 账号
      'password'=> '',    // 授权密码
      'port'=> 465,                       // 端口
      'charset'=> 'UTF-8',                // 编码
    ];
  }

}