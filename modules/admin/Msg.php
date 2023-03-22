<?php
namespace App\Admin;

use Service\Base;
use Service\Data;
use Service\AdminToken;

use Model\UserInfo;
use Model\UserMsg;

use Util\Util;

class Msg extends Base {

  /* 列表 */
	static function List() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 近7天
    $stime = strtotime('-7 days');
    $etime = time();
    $admin = AdminToken::Token($token);
    // 群组消息
    $m = new UserMsg();
    $m->Columns('id', 'gid', 'uid', 'fid', 'title', 'content', 'is_new', 'FROM_UNIXTIME(ctime) as time');
    $m->Where('(uid=? OR fid=?) AND ctime>=? AND ctime<=?', $admin->uid, $admin->uid, $stime, $etime);
    $m->Order('ctime');
    $all = $m->Find();
    $num=0; $uid=[]; $fid=[]; $tmp=[];
    foreach($all as $v){
      // 未读消息
      $n = !in_array($admin->uid, json_decode($v['is_new']))?1:0;
      // 系统信息
      if($v['gid']){
        $g_id = $v['gid'];
        $gid[] = $g_id;
        $tmp['g'.$g_id]['gid'] = $g_id;
        $tmp['g'.$g_id]['fid'] = $g_id;
        $tmp['g'.$g_id]['num'] += $n;
        $tmp['g'.$g_id]['time'] = $v['time'];
        $tmp['g'.$g_id]['msg'] = $v['content'];
        $tmp['g'.$g_id]['list'][] = $v;
      }else{
        // 用户消息
        $u_id = $v['uid']!=$admin->uid?$v['uid']:$v['fid'];
        $uid[] = $u_id;
        $fid[] = $v['fid'];
        $tmp['u'.$u_id]['gid'] = 0;
        $tmp['u'.$u_id]['fid'] = $u_id;
        $tmp['u'.$u_id]['num'] = $n;
        $tmp['u'.$u_id]['time'] = $v['time'];
        $tmp['u'.$u_id]['msg'] = $v['content'];
        $tmp['u'.$u_id]['list'][] = $v;
      }
      $num += $n;
    }
    // 查询
    $ids = array_values(array_unique(array_merge($uid, $fid)));
    $info = [];
    if($ids){
      $m = new UserInfo();
      $m->Columns('uid', 'name', 'img');
      $m->Where('uid in('.implode(',', $ids).')');
      $all = $m->Find();
      foreach($all as $v) $info[$v['uid']]=$v;
    }
    // 数据
    $list = [];
    foreach($tmp as $v1){
      // 数据
      $data = [];
      foreach($v1['list'] as $v2){
        $data[] = [
          'id'=>$v2['id'],
          'fid'=>$v2['uid'],
          'uid'=>$v2['fid'],
          'is_new'=>!in_array($admin->uid, json_decode($v2['is_new']))?true:false,
          'type'=>'msg',
          'msg'=> $v2['content'],
          'time'=> $v2['time'],
          'img'=>$v1['gid']?'':Data::Img($info[$v2['fid']]['img']),
        ];
      }
      // 列表
      $list[] = [
        'gid'=> $v1['gid'],
        'fid'=> $v1['fid'],
        'num'=> $v1['num'],
        'msg'=> $v1['msg'],
        'time'=> $v1['time'],
        'title'=> $v1['gid']?'系统消息':$info[$v1['fid']]['name'],
        'img'=> $v1['gid']?'':Data::Img($info[$v1['fid']]['img']),
        'list'=> $data,
      ];
    }
    // 排序
    $list = Util::AarraySort($list, 'time');
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'list'=>$list, 'num'=>$num]);
  }

  /* 搜索 */
	static function Sea() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $key = self::JsonName($json, 'key');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($key)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 查询
    $m = new UserInfo();
    $m->Columns('uid', 'name', 'img');
    $m->Where('name like ?', '%'.$key.'%');
    $m->Limit(0,10);
    $list = $m->Find();
    foreach($list as $k=>$v){
      $list[$k]['img'] = Data::Img($v['img']);
    }
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'list'=>$list]);
  }

  /* 阅读 */
	static function Read() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $ids = self::JsonName($json, 'ids');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($ids)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 更新
    $admin = AdminToken::Token($token);
    $ids = json_decode($ids);
    $m = new UserMsg();
    $m->Columns('id', 'is_new');
    $m->Where('id in('.implode(',', $ids).')');
    $all = $m->Find();
    foreach($all as $v){
      $arr = json_decode($v['is_new']);
      if(!in_array($admin->uid, $arr)){
        $arr[]=(string)$admin->uid;
        // 更新
        $m->Set(['is_new'=>json_encode($arr), 'utime'=>time()]);
        $m->Where('id=?', $v['id']);
        $m->Update();
      }else{
        return self::GetJSON(['code'=>500, 'msg'=>'成功']);
      }
    }
    return self::GetJSON(['code'=>0, 'msg'=>'成功']);
  }
  
}