<?php
namespace Library;

use Service\Base;
use Gregwar\Captcha\CaptchaBuilder;

class Captcha extends Base {

  static private $txtChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

  /* 验证码 */
  static function Vcode(int $num = 4): string {
    $code = Captcha::GetCode($num);
    $captcha = new CaptchaBuilder($code);
    $captcha->build();
    header('Content-type: image/jpeg');
    $captcha->output();
    return $code;
  }

  /* 获取号码 */
  static function GetCode(int $num): string {
    $code = '';
    for($i=0; $i<$num; $i++){
      $code .= substr(self::$txtChars,  mt_rand(0, strlen(self::$txtChars)-1), 1);
    }
    return $code;
  }

}