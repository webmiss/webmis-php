<?php
namespace App\Admin;

use Service\Base;
use Service\Data;
use Config\Env;
use Library\Safety;
use Library\Redis;
use Library\Captcha;
use Service\AdminToken;
use Model\User as UserM;

class User extends Base {

  /* 图形验证码 */
  static function Vcode(string $uname) {
    // 编码
    $code = Captcha::Vcode(4);
    // 缓存
    $redis = new Redis();
    $redis->Set('admin_vcode_'.$uname, strtolower($code));
    $redis->Expire('admin_vcode_'.$uname, 24*3600);
    $redis->Close();
  }

  /* 登录 */
	static function Login(){
    // 参数
    $json = self::Json();
    $uname = self::JsonName($json, 'uname');
    $passwd = self::JsonName($json, 'passwd');
    $vcode = self::JsonName($json, 'vcode');
    $vcode_url = Env::BaseUrl('admin/user/vcode').'/'.$uname.'?'.time();
    // 验证用户名
    if(!Safety::IsRight('uname',$uname) && !Safety::IsRight('tel',$uname) && !Safety::IsRight('email',$uname)){
      return self::GetJSON(['code'=>4000, 'msg'=>'请输入用户名/手机/邮箱!']);
    }
    // 登录方式
    $where = '';
    $vcode = strtolower(trim($vcode));
    if($passwd){
      // 密码长度
      if(!Safety::IsRight('passwd', $passwd)) return self::GetJSON(['code'=>4000, 'msg'=>'请输入6~16位密码!']);
      // 验证码
      $redis = new Redis();
      $code = $redis->Gets('admin_vcode_'.$uname);
      $redis->Close();
      if($code){
        if(strlen($vcode)!=4) return self::GetJSON(['code'=>4001,'msg'=>'请输入验证码!', 'vcode_url'=>$vcode_url]);
        elseif($vcode!=$code) return self::GetJSON(['code'=>4002,'msg'=>'验证码错误!', 'vcode_url'=>$vcode_url]);
      }
      // 条件
      $where = '(a.uname="'.$uname.'" OR a.tel="'.$uname.'" OR a.email="'.$uname.'") AND a.password="'.md5($passwd).'"';
    }else{
      // 验证码
      $redis = new Redis();
      $code = $redis->Gets('admin_code_'.$uname);
      $redis->Close();
      if(!$code || $code!=$vcode) return self::GetJSON(['code'=>4000, 'msg'=>'验证码错误']);
      // 清除
      $redis = new Redis();
      $redis->Expire('admin_code_'.$uname, 1);
      $redis->Close();
      // 条件
      $where = 'a.tel="'.$uname.'"';
    }
    // 查询
    $model = new UserM();
    $model->Table('user AS a');
    $model->LeftJoin('user_info AS b', 'a.id=b.uid');
    $model->LeftJoin('sys_perm AS c', 'a.id=c.uid');
    $model->LeftJoin('sys_role AS d', 'c.role=d.id');
    $model->Columns(
      'a.id', 'a.state', 'a.password',
      'b.type', 'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.birthday', 'b.img', 'b.signature',
      'c.perm', 'd.perm as role_perm'
    );
    $model->Where($where);
    $data = $model->FindFirst();
    // 是否存在
    if(empty($data)){
      // 缓存
      $redis = new Redis();
      $redis->Set('admin_vcode_'.$uname, time());
      $redis->Expire('admin_vcode_'.$uname, 24*3600);
      $redis->Close();
      return self::GetJSON(['code'=>4000,'msg'=>'帐号或密码错误!', 'vcode_url'=>$vcode_url]);
    }
    // 是否禁用
    if($data['state']!='1') return self::GetJSON(['code'=>4000,'msg'=>'该用户已被禁用!']);
    // 清除验证码
    $redis = new Redis();
    $redis->Expire('admin_vcode_'.$uname, 1);
    $redis->Close();
    // 默认密码
    $isPasswd = $data['password']==md5('123456');
    // 权限
    $perm = $data['role_perm'];
    if($data['perm']) $perm=$data['perm'];
    if(!$perm) return self::GetJSON(['code'=>4000,'msg'=>'该用户不允许登录!']);
    AdminToken::savePerm($data['id'], $perm);
    // 登录时间
    $ltime = time();
    $model->Table('user');
    $model->Set(['ltime'=>$ltime]);
    $model->Where('id=?', $data['id']);
    $model->Update();
    // Token
    $token = AdminToken::Create([
      'uid'=>$data['id'],
      'uname'=>$uname,
      'name'=>$data['name'],
      'type'=> $data['type'],
      'isPasswd'=> $isPasswd,
    ]);
    // 用户信息
    $uinfo = [
      'uid'=> $data['id'],
      'uname'=> $uname,
      'ltime'=> date('Y-m-d H:i:s', $ltime),
      'type'=> $data['type'],
      'nickname'=> $data['nickname'],
      'department'=> $data['department'],
      'position'=> $data['position'],
      'name'=> $data['name'],
      'gender'=> $data['gender'],
      'birthday'=> $data['birthday'],
      'img'=> Data::Img($data['img'], false),
      'signature'=> $data['signature'],
    ];
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'data'=>['token'=>$token, 'uinfo'=>$uinfo, 'isPasswd'=>$isPasswd]]);
  }

  /* Token验证 */
	static function Token(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $is_uinfo = self::JsonName($json, 'uinfo');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    $tData = AdminToken::Token($token);
    // 用户信息
    $uinfo = (object)[];
    if($is_uinfo){
      $m = new UserM();
      $m->Table('user AS a');
      $m->LeftJoin('user_info AS b', 'a.id=b.uid');
      $m->Columns(
        'FROM_UNIXTIME(a.ltime) as ltime', 'a.password',
        'b.type', 'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.img', 'b.signature', 'FROM_UNIXTIME(b.birthday, "%Y-%m-%d") as birthday'
      );
      $m->Where('id=?', $tData->uid);
      $uinfo = $m->FindFirst();
      $uinfo['uid'] = (string)$tData->uid;
      $uinfo['uname'] = $tData->uname;
      $uinfo['img'] = Data::Img($uinfo['img'], false);
    }
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'data'=>['token_time'=>$tData->time, 'uinfo'=>$uinfo, 'isPasswd'=>$tData->isPasswd]]);
  }

}