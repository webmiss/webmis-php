<?php
namespace Library\Baidu;

use Service\Base;
use Config\Baidu;
use Library\Curl;
use Library\Redis;

/* 百度Ai */
class Builder extends Base {

  static private $api_url = 'https://aip.baidubce.com/';

  /* 获取AccessToken */
  static function GetAccessTokenRedis() {
    // 配置
    $cfg = Baidu::Builder();
    // 是否缓存
    $redis = new Redis();
    $access_token = $redis->Gets($cfg['access_token']);
    $redis->Close();
    // 请求
    if(!$access_token) {
      $access_token = self::GetAccessToken();
      // 缓存
      $redis = new Redis();
      $redis->Set($cfg['access_token'], $access_token);
      $redis->Expire($cfg['access_token'], $cfg['refresh_time']);
      $redis->Close();
    }
    return $access_token;
  }

  /* GetAccessToken */
  static function GetAccessToken() {
    // 配置
    $cfg = Baidu::Builder();
    // 数据
    $headers = [
      'Content-Type'=> 'application/json',
    ];
    $data = [
      'grant_type'=> 'client_credentials',
      'client_id'=> $cfg['api_key'],
      'client_secret'=> $cfg['secret_key'],
    ];
    // 请求
    $res = Curl::Request(self::$api_url.'oauth/2.0/token', $data, 'POST', $headers);
    return isset($res->access_token)?$res->access_token:$res->error_description;
  }

  /* 对话 */
  static function GetMsg(array $messages=[], $token_mode='redis') {
    // 请求
    $headers = [
      'Content-Type'=> 'application/json',
    ];
    $data = [
      'model'=> 'deepseek-v3',
      'messages'=> $messages
    ];
    // 请求
    $access_token = $token_mode=='redis'?self::GetAccessTokenRedis():self::GetAccessToken();
    $res = Curl::Request(self::$api_url.'rpc/2.0/ai_custom/v1/wenxinworkshop/chat/completions_pro?access_token='.$access_token, json_encode($data), 'POST', $headers);
    return isset($res->result)?$res->result:$res->error_msg;
  }
  
}