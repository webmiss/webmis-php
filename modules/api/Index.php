<?php
namespace App\Api;

use Service\Base;
use Library\FileEo;
use Config\Env;

class Index extends Base {

  /* 首页 */
  static function Index(): string {
    return self::GetJSON(['code'=>0, 'msg'=>'PHP Api']);
  }

  /* 软件升级 */
  static function Version(): string {
    // 参数
    $json = self::Json();
    $os = self::JsonName($json, 'os');
    if(!in_array($os, ['android', 'Android', 'iOS'])) return self::GetJSON(['code'=>4000, 'msg'=>'['.$os.']该操作系统不支持更新!']);
    if($os==='Android') {
      $file = 'upload/app/'.$os.'.apk';
    }else if($os==='iOS') {
      $file = 'upload/app/'.$os.'.iso';
    }
    $size = FileEo::FileSize($file);
    self::Print($file);
    return self::GetJSON(['code'=>0, 'msg'=>'PHP Api', 'data'=>[
      'os'=> $os,
      'version'=> '3.0.0',
      'size'=> $size,
      'file'=> Env::BaseUrl($file),
    ]]);
  }

}
