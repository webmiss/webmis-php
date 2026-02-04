<?php
namespace App\Librarys;

use App\Config\Env;
use \Firebase\JWT\JWT;

/* 验证类 */
class Safety {

  /* 正则-公共 */
  static function IsRight(string $name='',string $value=''): bool {
    switch ($name) {
      case 'uname':
        return self::Test('^[a-zA-Z][a-zA-Z0-9\_\@\-\*\&]{3,15}$', $value);
      case 'passwd':
        return self::Test('^[a-zA-Z0-9|_|@|-|*|&]{6,16}$', $value);
      case 'tel':
        return self::Test('^1\d{10}$', $value);
      case 'email':
        return self::Test('^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$', $value);
      case 'idcard':
        return self::Test('^[1-9]\d{7}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}$|^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X)$', $value);
      default:
        return false;
    }
  }

  /* 正则-验证 */
  static function Test(string $reg, $value): bool {
    return preg_match('/'.$reg.'/', $value)?true:false;
  }

  /* Base64-加密 */
  static function Encode(array $param=[]): ?string {
    try{
      return JWT::encode($param, Env::$key, 'HS256');
    }catch (\Exception $e){
      return null;
    }
  }

  /* Base64-解密 */
  static function Decode(string $token=''): ?object {
    try{
      return JWT::decode($token, new \Firebase\JWT\Key(Env::$key, 'HS256'));
    }catch (\Exception $e){
      return null;
    }
  }

}