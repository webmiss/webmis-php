<?php
namespace App\Admin;

use Core\Controller;

/* 控制台 */
class Index extends Controller {

  /* 首页 */
  static function index() {
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'PHP Admin']);
  }

  /* 软件升级 */
  static function Version(): string {
    // 参数
    $json = self::Json();
    $os = self::JsonName($json, 'os');
    $local = self::JsonName($json, 'version');
    // 验证
    $os = strtolower($os);
    if(!in_array($os, ['web'])) return self::GetJSON(['code'=>4000, 'msg'=>'['.$os.']该操作系统不支持更新!']);
    // 数据
    $size = 0;
    $version = $url = '';
    if($os==='web') {
      $version = '3.0.0';
      $url = 'https://admin.webmis.vip';
      $size = 0;
    }
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['os'=>$os, 'version'=>$version, 'local'=>$local, 'size'=>$size, 'url'=>self::BaseUrl($url)]]);
  }

  /* 法定假期 */
  static function holiday(): string {
    // 参数
    $json = self::Json();
    $date = self::JsonName($json, 'date');
    $url = 'https://php.webmis.vip/upload/img/holiday/';
    // 假期
    $holiday = [
      '2026-02-16'=> ['holiday'=>true, 'name'=>'春节', 'img'=>$url.'20260216.png', 'bg'=>$url.'202602.png'],
      '2026-02-17'=> ['holiday'=>true, 'name'=>'春节', 'img'=>$url.'20260217.png', 'bg'=>$url.'202602.png'],
      '2026-02-18'=> ['holiday'=>true, 'name'=>'春节', 'img'=>$url.'20260218.png', 'bg'=>$url.'202602.png'],
      '2026-02-19'=> ['holiday'=>true, 'name'=>'春节', 'img'=>$url.'20260219.png', 'bg'=>$url.'202602.png'],
      '2026-02-20'=> ['holiday'=>true, 'name'=>'春节', 'img'=>$url.'20260220.png', 'bg'=>$url.'202602.png'],
      '2026-02-21'=> ['holiday'=>true, 'name'=>'春节', 'img'=>$url.'20260221.png', 'bg'=>$url.'202602.png'],
      '2026-02-22'=> ['holiday'=>true, 'name'=>'春节', 'img'=>$url.'20260222.png', 'bg'=>$url.'202602.png'],
      '2026-02-23'=> ['holiday'=>true, 'name'=>'春节', 'img'=>$url.'20260223.png', 'bg'=>$url.'202602.png'],
      '2026-04-04'=> ['holiday'=>true, 'name'=>'清明节', 'img'=>$url.'20260404.png', 'bg'=>$url.'202604.png'],
      '2026-04-05'=> ['holiday'=>true, 'name'=>'清明节', 'img'=>$url.'20260405.png', 'bg'=>$url.'202604.png'],
      '2026-04-06'=> ['holiday'=>true, 'name'=>'清明节', 'img'=>$url.'20260406.png', 'bg'=>$url.'202604.png'],
      '2026-05-01'=> ['holiday'=>true, 'name'=>'劳动节', 'img'=>'', 'bg'=>''],
      '2026-05-02'=> ['holiday'=>true, 'name'=>'劳动节', 'img'=>'', 'bg'=>''],
      '2026-05-03'=> ['holiday'=>true, 'name'=>'劳动节', 'img'=>'', 'bg'=>''],
      '2026-05-04'=> ['holiday'=>true, 'name'=>'劳动节', 'img'=>'', 'bg'=>''],
      '2026-05-05'=> ['holiday'=>true, 'name'=>'劳动节', 'img'=>'', 'bg'=>''],
      '2026-06-20'=> ['holiday'=>true, 'name'=>'端午节', 'img'=>'', 'bg'=>''],
      '2026-06-21'=> ['holiday'=>true, 'name'=>'端午节', 'img'=>'', 'bg'=>''],
      '2026-06-22'=> ['holiday'=>true, 'name'=>'端午节', 'img'=>'', 'bg'=>''],
      '2026-09-26'=> ['holiday'=>true, 'name'=>'中秋节', 'img'=>'', 'bg'=>''],
      '2026-09-27'=> ['holiday'=>true, 'name'=>'中秋节', 'img'=>'', 'bg'=>''],
      '2026-09-28'=> ['holiday'=>true, 'name'=>'中秋节', 'img'=>'', 'bg'=>''],
      '2026-10-01'=> ['holiday'=>true, 'name'=>'国庆节', 'img'=>'', 'bg'=>''],
      '2026-10-02'=> ['holiday'=>true, 'name'=>'国庆节', 'img'=>'', 'bg'=>''],
      '2026-10-03'=> ['holiday'=>true, 'name'=>'国庆节', 'img'=>'', 'bg'=>''],
      '2026-10-04'=> ['holiday'=>true, 'name'=>'国庆节', 'img'=>'', 'bg'=>''],
      '2026-10-05'=> ['holiday'=>true, 'name'=>'国庆节', 'img'=>'', 'bg'=>''],
      '2026-10-06'=> ['holiday'=>true, 'name'=>'国庆节', 'img'=>'', 'bg'=>''],
      '2026-10-07'=> ['holiday'=>true, 'name'=>'国庆节', 'img'=>'', 'bg'=>''],
    ];
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>isset($holiday[$date])?$holiday[$date]:'']);
  }

}
