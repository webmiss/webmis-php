<?php
namespace Library;

use Service\Base;
use Config\Env;
use Util\Base64;

/* 上传类 */
class Upload extends Base {

  /* 单文件 */
  static function File($file, array $cfg=[]): string {
    // 参数
    $param = array_merge([
      'path'=>'upload/',  //上传目录
      'filename'=>'', //文件名
      'bind'=>['svg','jpg','jpeg','png','gif','mov','mp4','wav','mp3'], //允许格式
    ],$cfg);
    // 限制格式
    $ext = FileEo::GetExt($file['name']);
    if($param['bind']){
      if(!in_array($ext,$param['bind'])){
        self::Print('只支持'.implode(',',$param['bind']).'格式!');
        return '';
      }
    }
    // 是否重命名
    $param['filename'] = $param['filename']?$file['name']:$param['filename'].'.'.$ext;
    // 创建目录
    if(!FileEo::Mkdir($param['path'])){
      self::Print('[Upload] Mkdir:', '创建目录失败!');
      return '';
    }
    // 保存文件
    if(!FileEo::Upload($file['tmp_name'],$param['path'].$param['filename'])){
      self::Print('[Upload] Upload:', '保存文件失败!');
      return '';
    }
    return $param['filename'];
  }

  /* Base64 */
  static function Base64(string $file, string $base64): string {
    // 否有类型
    $ct = explode(',', $base64);
    if(count($ct)>1){
      $param['ext'] = Base64::GetExt($ct[0]);
      $base64 = $ct[1];
    }
    // 保存文件
    return FileEo::Writer($file, base64_decode($base64))?$file:'';
  }

  /* 图片回收 */
  static function HtmlImgClear(string $html, string $dir): bool {
    // 全部图片
    $imgs = self::GetHtmlFile($html);
    // 清理图片
    $all = FileEo::AllFile($dir);
    foreach($all as $val) {
      if(!in_array($val, $imgs)) FileEo::RemoveAll($dir.$val);
    }
    return true;
  }

  /* 文件名-生成 */
  static function GetFileName(): string {
    list($msec, $sec) = explode(' ', microtime());
    $randA = (string)mt_rand(0, 255);
    $randB = (string)mt_rand(0, 255);
    return date('YmdHis') . substr($msec,2,3) . Env::$machine_id . $randA . $randB;
  }

  /* 图片地址-获取HTML */
  static function GetHtmlFile(string $html): array {
    $pattern = '/<img.*?src=[\'|\"](.*?)[\'|\"].*?[\/]?>/';
    preg_match_all($pattern, htmlspecialchars_decode($html), $match);
    $imgs = [];
    foreach($match[1] as $val){
      $imgs[] = basename($val);
    }
    return $imgs;
  }

}