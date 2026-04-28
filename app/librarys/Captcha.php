<?php
namespace App\Librarys;

use Core\Base;
use Gregwar\Captcha\CaptchaBuilder;

/* 验证码 */
class Captcha extends Base {

  /* 字符集 */
  static private $txtChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

  /* 获取字符 */
  static function GetCode(int $num): string {
    $code = '';
    for($i=0; $i<$num; $i++){
      $code .= substr(self::$txtChars,  mt_rand(0, strlen(self::$txtChars)-1), 1);
    }
    return $code;
  }

  /* 获取数字 */
  static function GetNum(int $num): string {
    $code = '';
    for($i=0; $i<$num; $i++){
      $code .= mt_rand(0, 9);
    }
    return $code;
  }

  /* 图形验证码 */
  static function Vcode(int $num = 4): array {
    $code = Captcha::GetCode($num);
    $captcha = new CaptchaBuilder($code);
    $captcha->build(140, 40);
    $img = $captcha->get();
    return [$code, $img];
  }

}