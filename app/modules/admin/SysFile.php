<?php
namespace App\Admin;

use Core\Controller;
use App\Librarys\FileEo;
use App\Librarys\Upload;
use App\Service\TokenAdmin;

/* 文件管理 */
class SysFile extends Controller {

  private static $dirRoot = 'upload/';

  /* 列表 */
  static function List(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $path = self::JsonName($json, 'path');
    // 验证
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($path)) return self::GetJSON(['code'=>4000]);
    // 数据
    FileEo::$Root = self::$dirRoot;
    $list = FileEo::List($path);
    // 返回
    return self::GetJSON([
      'code'=> 0,
      'time'=> date('Y/m/d H:i:s'),
      'data'=> ['url'=>self::BaseUrl(self::$dirRoot), 'list'=>$list],
    ]);
  }

  /* 新建文件夹 */
  static function Mkdir(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $path = self::JsonName($json, 'path');
    $name = self::JsonName($json, 'name');
    // 验证
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($path) || empty($name)) return self::GetJSON(['code'=>4000]);
    // 数据
    FileEo::$Root = self::$dirRoot;
    if(!FileEo::Mkdir($path.$name)) return self::GetJSON(['code'=>5000]);
    // 返回
    return self::GetJSON(['code'=>0]);
  }

  /* 重命名 */
  static function Rename(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $path = self::JsonName($json, 'path');
    $name = self::JsonName($json, 'name');
    $rename = self::JsonName($json, 'rename');
    // 验证
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($path) || empty($rename) || empty($name)) return self::GetJSON(['code'=>4000]);
    // 数据
    FileEo::$Root = self::$dirRoot;
    if(!FileEo::Rename($path.$rename, $path.$name)) return self::GetJSON(['code'=>5000]);
    // 返回
    return self::GetJSON(['code'=>0]);
  }

  /* 删除 */
  static function Remove(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $path = self::JsonName($json, 'path');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($path) || empty($data) || !is_array($data)) return self::GetJSON(['code'=>4000]);
    // 数据
    FileEo::$Root = self::$dirRoot;
    foreach($data as $v) FileEo::RemoveAll($path.$v);
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功']);
  }

  /* 上传 */
  static function Upload(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $path = self::JsonName($json, 'path');
    // 验证
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($path)) return self::GetJSON(['code'=>4000]);
    // 数据
    $file = $_FILES['file'];
    $img = Upload::File($file, ['path'=>self::$dirRoot.$path, 'filename'=>$file['name'], 'bind'=>null]);
    if(empty($img)) return self::GetJSON(['code'=>5000]);
    // 返回
    return self::GetJSON(['code'=>0]);
  }

  /* 下载 */
  static function Down(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $path = self::JsonName($json, 'path');
    $filename = self::JsonName($json, 'filename');
    // 验证
    $msg = TokenAdmin::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($path) || empty($filename)) return self::GetJSON(['code'=>4000]);
    // 数据
    FileEo::$Root = self::$dirRoot;
    $data = FileEo::Bytes($path.$filename);
    // 返回
    self::GetFile($data, ['Content-type'=>'application/octet-stream']);
  }

}