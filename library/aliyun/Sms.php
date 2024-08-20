<?php
namespace Library\Aliyun;

use Config\Aliyun;
use Service\Base;
use Library\Curl;

/* 短信服务 */
class Sms extends Base {

  private static $Url = 'http://dysmsapi.aliyuncs.com/';

  /* 发送 */
  static function Send($tel, $sign, $template, $param=[]): bool {
    $cfg = Aliyun::RAM();
    $data = [
      'AccessKeyId'=> $cfg['AccessKeyId'],
      'PhoneNumbers'=> $tel,
      'SignName'=> $sign,
      'TemplateCode'=> $template,
      'TemplateParam'=> json_encode($param),
      'Action'=> 'SendSms',
      'Format'=> 'JSON',
      'Version'=> '2017-05-25',
      'SignatureVersion'=> '1.0',
      'SignatureMethod'=> 'HMAC-SHA1',
      'SignatureNonce'=> uniqid(),
      'Timestamp'=> gmdate('Y-m-d\TH:i:s\Z'),
    ];
    $data['Signature'] = self::GetSign($data, $cfg['AccessKeySecret']);
    // 请求
    $res = Curl::Request(self::$Url, $data);
    return $res->Code=='OK'?true:false;
  }

  /* 签名 */
  static function GetSign($param, $accessKeySecret){
    ksort($param);
    $str = 'GET&%2F&'.self::special(http_build_query($param));
    $signature = base64_encode(hash_hmac('sha1', $str, $accessKeySecret.'&', true));
    return $signature;
  }
  private static function special($str): string {
    $str = urlencode($str);
    $str = preg_replace('/\+/', '%20', $str);
    $str = preg_replace('/\*/', '%2A', $str);
    $str = preg_replace('/%7E/', '~', $str);
    return $str;
  }
  
}