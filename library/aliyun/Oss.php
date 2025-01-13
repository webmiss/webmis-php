<?php
namespace Library\Aliyun;

use Config\Aliyun;
use Service\Base;
use Library\Curl;
use Util\Util;

/* 对象存储 */
class Oss extends Base {

  /* 公共参数 */
  static function GetParam(string $file): array {
    $cfg = Aliyun::OSS();
    return [
      'url'=> 'http://'.$cfg['Bucket'].'.'.$cfg['Endpoint'].'/'.$file,
      'resource'=> '/'.$cfg['Bucket'].'/'.$file,
      'date'=> gmdate("D, d M Y H:i:s T"),
    ];
  }

  /* 上传 */
  static function PutObject(string $file, string $content, string $content_type='', array $oss_headers=[]): bool {
    // 参数
    $method = 'PUT';
    $param = self::GetParam($file);
    $authorization = self::Authorization($method, '', $content_type, $param['date'], $oss_headers, $param['resource']);
    $headers = array_merge([
      "Date"=> $param['date'],
      "Content-Type"=> $content_type,
      'Authorization'=> $authorization,
    ], $oss_headers);
    // 请求
    $res = Curl::Request($param['url'], base64_decode($content), $method, $headers, 'xml');
    return !$res?true:false;
  }

  /* 删除-单个 */
  static function DeleteObject(string $file, array $oss_headers=[]): bool {
    // 参数
    $method = 'DELETE';
    $param = self::GetParam($file);
    $authorization = self::Authorization($method, '', '', $param['date'], $oss_headers, $param['resource']);
    $headers = array_merge([
      "Date"=> $param['date'],
      "Content-Type"=> '',
      'Authorization'=> $authorization,
    ], $oss_headers);
    // 请求
    $res = Curl::Request($param['url'], '', $method, $headers, 'xml');
    return !$res?true:false;
  }

  /* 签名 */
  static function Authorization($method, $content_md5, $content_type, $date, $oss_headers, $resource){
    $cfg = Aliyun::RAM();
    $headers = '';
    foreach($oss_headers as $k=>$v) $headers .=$k.':'.$v."\n";
    $str = $method."\n".$content_md5."\n".$content_type."\n".$date."\n".$headers.$resource;
    $signature = base64_encode(hash_hmac('sha1', $str, $cfg['AccessKeySecret'], true));
    return "OSS " . $cfg['AccessKeyId'] . ":" . $signature;
  }

  /* 签名直传 */
  static function PolicySign(int $expireTime, int $maxSize=0): array {
    // 配置
    $cfg = Aliyun::RAM();
    $conditions = [];
    // 限制大小
    $conditions[] = ['content-length-range', 0, $maxSize];
    // 超时时间
    $now = time();
    $expire = $now + $expireTime;
    $expiration = Util::GmtISO8601($expire);
    // 签名数据
    $policyStr = json_encode(['expiration'=>$expiration, 'conditions'=>$conditions]);
    $policy = base64_encode($policyStr);
    $signature = base64_encode(hash_hmac('sha1', $policy, $cfg['AccessKeySecret'], true));
    // 返回
    return [
      'accessid'=> $cfg['AccessKeyId'],
      'policy'=> $policy,
      'signature'=> $signature,
      'expire'=> $expire,
    ];
  }

  /* 签名直传-回调方式 */
  static function Policy(string $file, int $expireTime=0, int $maxSize=0): array {
    $ram = Aliyun::RAM();
    $cfg = Aliyun::OSS();
    // 默认值
    if($expireTime==0) $expireTime = $cfg['ExpireTime'];
    if($maxSize==0) $maxSize = $cfg['MaxSize'];
    // 数据
    $res = self::PolicySign($expireTime, $maxSize);
    $res['host'] = 'https://'.$cfg['Bucket'].'.'.$cfg['Endpoint'];
    $res['key'] = $file;
    $res['max_size'] = $maxSize;
    // 回调
    $callbackBody = json_encode([
      'key'=> $file,
      'expire'=> $res['expire'],
      'sign'=> md5($file.'&'.$res['expire'].'&'.$ram['AccessKeySecret']),
    ]);
    $callbackData = json_encode([
      'callbackUrl'=> $cfg['CallbackUrl'],
      'callbackBodyType'=> $cfg['CallbackType'],
      'callbackBody'=> $callbackBody,
    ]);
    $res['callback'] = base64_encode($callbackData);
    return $res;
  }

  /* 签名直传-验证 */
  static function PolicyVerify(string $file, string $expire, string $sign): bool {
    // 配置
    $ram = Aliyun::RAM();
    // 验证
    $signTmp = md5($file.'&'.$expire.'&'.$ram['AccessKeySecret']);
    if($sign != $signTmp) return false;
    // 是否超时
    $now = time();
    $etime = (int)$expire;
    if($now > $etime) return false;
    return true;
  }

}