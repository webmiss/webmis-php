<?php
namespace App\Api;

use Config\Env;
use Service\Base;
use Library\FileEo;
use Model\WebHtml;

class Index extends Base {

  /* 首页 */
  static function Index(): string {
    return self::GetJSON(['code'=>0, 'msg'=>'PHP Api']);
  }

  /* Html */
  static function GetHtml(): string {
    // 参数
    $json = self::Json();
    $name = self::JsonName($json, 'name');
    if(empty($name)) return self::GetJSON(['code'=>4000]);
    // 查询
    $m = new WebHtml();
    $m->Columns('id', 'title', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime', 'content');
    $m->Where('name=? AND status=1', $name);
    $one = $m->FindFirst();
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>$one]);
  }

  /* 软件升级 */
  static function Version(): string {
    // 参数
    $json = self::Json();
    $os = self::JsonName($json, 'os');
    $version = self::JsonName($json, 'version');
    $os = strtolower($os);
    if(!in_array($os, ['android', 'ios'])) return self::GetJSON(['code'=>4000, 'msg'=>'['.$os.']该操作系统不支持更新!']);
    if($os==='android') {
      $file = 'upload/app/vip.webmis.m.apk';
      $size = FileEo::FileSize($file);
    }else if($os==='ios') {
      $file = 'itms-apps://itunes.apple.com/cn/app/tao-bao-sui-shi-sui-xiang/id387682726?mt=8';
      $size = FileEo::FileSize($file);
    }
    return self::GetJSON(['code'=>0, 'data'=>[
      'os'=> $os,
      'version'=> '3.0.0',
      'size'=> $size,
      'file'=> Env::BaseUrl($file),
    ]]);
  }

}
