<?php
namespace App\Home;

use Service\Base;
use Library\FileEo;
use Library\Aliyun\Oss;

/* 回调 */
class Callback extends Base {

  /* OSS-上传回调 */
  static function OssCallback() {
    // 验证
    $param = self::Json();
    $dir = isset($param['dir'])?(string)$param['dir']:'';
    $file = isset($param['file'])?(string)$param['file']:'';
    $expire = isset($param['expire'])?(string)$param['expire']:'';
    $sign = isset($param['sign'])?(string)$param['sign']:'';
    if(!Oss::PolicyVerify($dir, $file, $expire, $sign)) return '';
    FileEo::WriterEnd('upload/callback_oss.json', json_encode($param));
    return self::GetJSON(['Status'=>'Ok']);
  }

}