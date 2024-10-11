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
      'AppId'=> '3e66d07a-410d-4eac-9ae5-4280d9daf687',
      'Authorization'=> 'bce-v3/ALTAK-oVHJSughcuRo4KZGLKuxp/e6efe47a053a2138fb1856b77ff151b162e9311e',
      'conversation_id'=> 'baidu_conversation_id',            // Redis名称
      'refresh_time'=> 3600*24*6                              // 刷新间隔(秒)
    ];
  }

}