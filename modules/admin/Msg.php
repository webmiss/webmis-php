<?php
namespace App\Admin;

use Service\Base;
use Service\Data;
use Service\AdminToken;
use Config\Socket as cfg;
use Data\Msg as MsgD;
use Library\Socket;
use Model\UserInfo;

/* 消息 */
class Msg extends Base {

  /* Socket */
  static function Socket(){
    $uid = $_GET['uid'];
    $msg = $_GET['msg'];
    if(empty($uid) || $msg=='') return;
    Socket::Send('admin', ['gid'=>1, 'uid'=>$uid, 'fid'=>0, 'type'=>'msg', 'title'=>cfg::$name[1], 'content'=>$msg]);
  }

  /* 列表 */
	static function List() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 数据
    $admin = AdminToken::Token($token);
    list($list, $num) = MsgD::GetList($admin->uid, 'name');
    return self::GetJSON(['code'=>0, 'data'=>['num'=>$num, 'list'=>$list]]);
  }

  /* 搜索 */
	static function Sea() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $key = self::JsonName($json, 'key');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($key)) {
      return self::GetJSON(['code'=>4000]);
    }
    $admin = AdminToken::Token($token);
    // 查询
    $m = new UserInfo();
    $m->Columns('uid', 'name as title', 'img');
    $m->Where('name like ?', '%'.$key.'%');
    $m->Limit(0,10);
    $list = $m->Find();
    foreach($list as $k=>$v){
      $list[$k]['gid'] = 0;
      $list[$k]['fid'] = $v['uid'];
      $list[$k]['uid'] = $admin->uid;
      $list[$k]['img'] = Data::Img($v['img']);
    }
    // Ai助理
    $list = array_merge([
      ['gid'=>1, 'fid'=>0, 'uid'=>1, 'title'=>cfg::$name[1], 'img'=>''],
    ], $list);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>$list]);
  }

  /* 阅读 */
	static function Read() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $ids = self::JsonName($json, 'ids');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(!is_array($ids) || empty($ids)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 更新
    $admin = AdminToken::Token($token);
    $res = MsgD::Read($admin->uid, $ids);
    // 返回
    if($res) {
      return self::GetJSON(['code'=>0]);
    }else{
      return self::GetJSON(['code'=>5000]);
    }
  }
  
}