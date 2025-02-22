<?php
namespace Config;

/* 百度 */
class Baidu {

  /* 统计-商业账号 */
  static function TongJi(): array {
    return [
      'UserName'=> 'kingsoul',                        //用户名
      'PassWord'=> 'eckingsoul',                      //密码
      'Token'=> 'c67cde72015a76798c707b170fc6987e',   //Token
      'AccountType'=> 1,                              //账户类型
    ];
  }

  /* Ai-模型 */
  static function Builder(): array {
    return [
      'api_key'=> '8LXwrNzJe7Pp0mi2z0AKGRs3',
      'secret_key'=> 'tN47BUUKpsxzCddhdTcnWwoePmA2Wrpl',
      'access_token'=> 'baidu_access_token',                  // Redis名称
      'refresh_time'=> 3600*24*6                              // 刷新间隔(秒)
    ];
  }

}