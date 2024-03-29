<?php
namespace App\Admin;

use Service\Base;
use Service\Data;
use Service\AdminToken;
use Library\Aliyun\Oss;
use Model\UserInfo as UserInfoM;
use Util\Util;

class UserInfo extends Base {

  /* 列表 */
	static function List(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    $tData = AdminToken::Token($token);
    // 查询
    $model = new UserInfoM();
    $model->Columns('nickname', 'name', 'gender', 'FROM_UNIXTIME(birthday, "%Y-%m-%d") as birthday', 'department', 'position', 'img');
    $model->Where('uid=?', $tData->uid);
    $list = $model->FindFirst();
    // 数据
    $list['img'] = Data::Img($list['img']);
    // 返回
    return self::GetJSON(['code'=>0,'msg'=>'成功','list'=>$list]);
  }

  /* 编辑 */
  static function Edit(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    $tData = AdminToken::Token($token);
    if(empty($data)) return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    // 数据
    $param = json_decode($data);
    $model = new UserInfoM();
    $info = [
      'nickname'=> trim($param->nickname),
      'name'=> trim($param->name),
      'gender'=> trim($param->gender),
      'birthday'=> Util::StrToTime($param->birthday),
      'department'=> trim($param->department),
      'position'=> trim($param->position),
    ];
    $model->Set($info);
    $model->Where('uid=?', $tData->uid);
    $model->Update();
    // 返回
    $info['uname'] = $tData->uname;
    $info['img'] = $param->img;
    $info['birthday'] = date('Y-m-d', $info['birthday']);
    return self::GetJSON(['code'=>0,'msg'=>'成功','uinfo'=>$info]);
  }

  /* 头像 */
  static function Upimg(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $base64 = self::JsonName($json, 'base64');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($base64)) return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    // 限制格式
    $extAll = [
      'data:image/jpeg;base64' => 'jpg',
      'data:image/png;base64' => 'png',
    ];
    $ct = explode(',', $base64);
    $ext = $extAll[$ct[0]];
    if(!$ext) return self::GetJSON(['code'=>400, 'msg'=>'只能上传JPG、PNG格式图片!']);
    // OSS
    $admin = AdminToken::Token($token);
    $file = 'user/img/'.$admin->uid.'.jpg';
    $res = Oss::PutObject($file, $ct[1]);
    if(!$res) return self::GetJSON(['code'=>5000, 'msg'=>'上传失败!']);
    // 保存图片
    $m = new UserInfoM();
    $m->Set(['img'=>$file]);
    $m->Where('uid=?', $admin->uid);
    if(!$m->Update()) return self::GetJSON(['code'=>5000, 'msg'=>'请重新上传!']);
    // 返回
    return self::GetJSON(['code'=>0,'msg'=>'成功', 'img'=>Data::Img($file, false)]);
  }

}