<?php
namespace Library\Baidu;

use Service\Base;
use Config\Baidu;
use Library\Curl;
use Library\Redis;
use Util\Util;

/* 百度Ai */
class Builder extends Base {

  static private $api_url = 'https://qianfan.baidubce.com/v2/app/';

  /* 新建会话 */
  static function GetConversationId(array $cfg=[]) : string {
    // 配置
    $cfg = $cfg?:Baidu::Builder();
    // 是否缓存
    $redis = new Redis();
    $conversation_id = $redis->Gets($cfg['conversation_id']);
    $redis->Close();
    if(!$conversation_id) {
      // 请求
      $headers = [
        'X-Appbuilder-Authorization'=> 'Bearer '.$cfg['Authorization'],
        'Content-Type'=> 'application/json',
      ];
      $data = ['app_id'=> $cfg['AppId']];
      // 请求
      $res = Curl::Request(self::$api_url.'conversation', json_encode($data), 'POST', $headers);
      if(!isset($res->conversation_id)) return '';
      // 缓存
      $redis = new Redis();
      $redis->Set($cfg['conversation_id'], $res->conversation_id);
      $redis->Expire($cfg['conversation_id'], $cfg['refresh_time']);
      $redis->Close();
      return $res->conversation_id;
    }
    return $conversation_id;
  }

  /* 对话内容 */
  static function GetAnswer(array $param=[]) : string {
    // 配置
    $cfg = Baidu::Builder();
    $conversation_id = self::GetConversationId($cfg);
    // 请求
    $headers = [
      'X-Appbuilder-Authorization'=> 'Bearer '.$cfg['Authorization'],
      'Content-Type'=> 'application/json',
    ];
    $data = array_merge([
      'app_id'=> $cfg['AppId'],
      'conversation_id'=> $conversation_id,
      'stream'=> false,
      'query'=> 'WebMIS',
    ], $param);
    // 请求
    $res = Curl::Request(self::$api_url.'conversation/runs', json_encode($data), 'POST', $headers);
    return isset($res->answer)?$res->answer:'';
  }
  
}