<?php
namespace App\Service;

use Core\Base;
use Core\Redis;
use App\Config\Env;
use App\Library\Safety;

/* Token-验证 */
class TokenSupplier extends Base {

  /* 验证 */
  static function Verify(string $token): string {
    // Token
    if($token=='') return 'Token不能为空!';
    $tData = Safety::Decode($token);
    if(!$tData) return 'Token验证失败!';
    // 是否过期
    $uid = (string)$tData->uid;
    $key = Env::$supplier_token_prefix.'_token_'.$uid;
    $redis = new Redis();
    $access_token = $redis->Gets($key);
    $time = $redis->Ttl(Env::$supplier_token_prefix.'_token_'.$uid);
    $redis->Close();
    if(Env::$supplier_token_sso && md5($token)!=$access_token) return '强制退出!';
    if($time<1) return '请重新登录!';
    // 续期
    if(Env::$supplier_token_auto){
      $redis = new Redis();
      $redis->Expire(Env::$supplier_token_prefix.'_token_'.$uid, Env::$supplier_token_time);
      $redis->Close();
    }
    return '';
  }

  /* 生成 */
  static function Create(array $data): ?string {
    $data['l_time'] = date('Y-m-d H:i:s');
    $token = Safety::Encode($data);
    // 缓存
    $redis = new Redis();
    $key = Env::$supplier_token_prefix.'_token_'.$data['uid'];
    $redis->Set($key, md5($token));
    $redis->Expire($key, Env::$supplier_token_time);
    $redis->Close();
    return $token;
  }

  /* 解析 */
  static function Token(string $token): ?object {
    $token = Safety::Decode($token);
    if($token){
      $redis = new Redis();
      $token->time = $redis->Ttl(Env::$supplier_token_prefix.'_token_'.$token->uid);
      $redis->Close();
    }
    return $token;
  }

  /* 是否认证 */
  static function IsVerify(string $token): bool {
    $admin = self::Token($token);
    return $admin->isVerify;
  }

}