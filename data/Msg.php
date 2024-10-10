<?php
namespace Data;

use Service\Base;
use Service\Data as sData;
use Config\Socket as cfg;
use Model\UserInfo;
use Model\UserMsg;
use Util\Util;


/* 消息 */
class Msg extends Base {

  /* 列表 */
  static function GetList(int $uid, string $name='nickname', int $days=30): array {
    // 近30天
    $stime = strtotime('-'.$days.' days');
    $etime = time();
    // 消息
    $num = 0;
    $m = new UserMsg();
    $m->Columns('id', 'gid', 'uid', 'fid', 'format', 'is_new', 'content', 'FROM_UNIXTIME(ctime) as time');
    $m->Where('(uid=? OR fid=?) AND ctime>=? AND ctime<=?', $uid, $uid, $stime, $etime);
    $m->Order('ctime');
    $all = $m->Find();
    // 用户信息
    $uids = [];
    foreach($all as $v){
      $uids[$v['uid']] = 1;
      $uids[$v['fid']] = 1;
    }
    $uinfo = self::GetUserInfo(array_keys($uids));
    // 数据
    $list = [];
    foreach($all as $v){
      // 未读消息
      $n = $v['is_new']&&in_array($uid, json_decode($v['is_new']))?0:1;
      $num += $n;
      // 系统信息
      if($v['gid']){
        $title = cfg::$name[1];
        $img = '';
        // 信息
        $tid = 'g_'.$v['gid'];
        $list[$tid]['type'] = 'group';
        $list[$tid]['gid'] = $v['gid'];
        $list[$tid]['fid'] = 0;
        $list[$tid]['title'] = $title;
        $list[$tid]['img'] = $img;
      }else{
        $id = $v['uid']!=$uid?$v['uid']:$v['fid'];
        $title = $uinfo[$id][$name];
        $img = sData::Img($uinfo[$id]['img']);
        // 信息
        $tid = 'm_'.$id;
        $list[$tid]['type'] = 'msg';
        $list[$tid]['gid'] = 0;
        $list[$tid]['fid'] = $id;
        $list[$tid]['title'] = $title;
        $list[$tid]['img'] = $img;
      }
      // 列表
      $list[$tid]['num'] = isset($list[$tid]['num'])?$list[$tid]['num']+$n:0;
      $list[$tid]['time'] = $v['time'];
      $list[$tid]['content'] = $v['content'];
      $list[$tid]['list'][] = [
        'id'=> $v['id'],
        'format'=> $v['format'],
        'uid'=> $v['uid'],
        'fid'=> $v['fid'],
        'is_new'=> $n?true:false,
        'time'=> $v['time'],
        'title'=> $title,
        'img'=> $img,
        'content'=> $v['content'],
      ];
    }
    // 排序
    $list = array_values($list);
    $list = Util::AarraySort($list, 'time');
    return [$list, $num];
  }

  /* 用户信息 */
  static function GetUserInfo(array $ids=[]){
    $list = [];
    if(!$ids) return $list;
    // 查询
    $m = new UserInfo();
    $m->Columns('uid', 'nickname', 'name', 'img');
    $m->Where('uid in('.implode(',', $ids).')');
    $all = $m->Find();
    foreach($all as $v) $list[$v['uid']]=$v;
    return $list;
  }

  /* 阅读 */
	static function Read($uid, $ids): bool {
    $m = new UserMsg();
    $m->Columns('id', 'is_new');
    $m->Where('id in('.implode(',', $ids).')');
    $all = $m->Find();
    foreach($all as $v){
      $arr = $v['is_new']?json_decode($v['is_new']):[];
      if(!in_array($uid, $arr)){
        $arr[]=(string)$uid;
        // 更新
        $m->Set(['is_new'=>json_encode($arr), 'utime'=>time()]);
        $m->Where('id=?', $v['id']);
        $m->Update();
      }else{
        continue;
      }
    }
    return true;
  }

}