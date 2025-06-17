<?php
namespace Library\Aliyun;

use Config\Aliyun;
use Service\Base;
use Library\Curl;

/* 阿里云百炼 */
class Bailian extends Base {

  static private $api_url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';

  /* 对话 */
  static function GetMsg(array $messages=[], $token_mode='redis') {
    $cfg = Aliyun::BaiLian();
    // 请求
    $headers = [
      'Content-Type'=> 'application/json',
      'Authorization'=> 'Bearer '.$cfg['ApiKey'],
    ];
    $data = [
      'model'=> 'qwen-turbo-latest',
      'messages'=> $messages
    ];
    $res = Curl::Request(self::$api_url, json_encode($data), 'POST', $headers);
    return isset($res->choices)?$res->choices[0]->message->content:$res->error->message;
  }

}