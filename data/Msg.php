<?php
namespace Data;

use Service\Base;
use Service\Data as sData;

use Config\Socket as cfg;
use Model\UserMsg;
use Model\UserInfo;


/* 消息 */
class Msg extends Base {

  // 名称字段
  static public $name='name';

  /* 列表 */
  static function GetList(int $uid, int $days=30): array {
    // 日期
    $start = strtotime('-'.$days.' days');
    $end = time();
    $pname = sData::PartitionName($start, $end);
    // 消息
    $num = 0;
    $m = new UserMsg();
    $m->Partition($pname);
    $m->Columns('id', 'gid', 'fid', 'uid', 'format', 'is_new', 'content', 'FROM_UNIXTIME(ctime) as time');
    $m->Where('(fid=? OR uid=?) AND ctime>=? AND ctime<=? AND is_del NOT LIKE "%\"'.$uid.'\"%"', $uid, $uid, $start, $end);
    $m->Order('id DESC');
    $all = $m->Find();
    $list = [];
    foreach($all as $v) {
      // 未读消息
      $n = $v['is_new']&&in_array($uid, json_decode($v['is_new']))?0:1;
      $num += $n;
      $fid = $v['fid']===$uid?$v['uid']:$v['fid'];
      if(!isset($list[$fid])) {
        $list[$fid] = [
          'gid'=> $v['gid'],
          'fid'=> $fid,
          'num'=> $n,
          'time'=> $v['time'],
          'format'=> $v['format'],
          'title'=> '',
          'img'=> '',
          'content'=> $v['format']===0?$v['content']:json_decode($v['content'], true),
        ];
        // 群组
        if($v['gid']) {
          $list[$fid]['title'] = cfg::$service[$v['gid']]['title'];
          $list[$fid]['img'] = cfg::$service[$v['gid']]['img'];
        }
      } else {
        $list[$fid]['num'] += $n;
      }
    }
    // 用户
    if($list) {
      $info = self::GetInfo(array_keys($list));
      foreach($info as $k=>$v) {
        $list[$k]['title'] = $v['title'];
        $list[$k]['img'] = $v['img'];
      }
    }
    $list = array_values($list);
    return [$num, $list];
  }

  /* 明细 */
  static function GetShow(int $gid, int $fid, int $uid, int $page=1, int $limit=30): array {
    $m = new UserMsg();
    $m->Columns('id', 'gid', 'fid', 'uid', 'format', 'is_new', 'content', 'FROM_UNIXTIME(ctime) as time');
    $m->Where('gid=? AND (fid=? AND uid=? OR fid=? AND uid=?) AND is_del NOT LIKE "%\"'.$uid.'\"%"', $gid, $fid, $uid, $uid, $fid);
    $m->Page($page, $limit);
    $m->Order('id DESC');
    $list = $m->Find();
    // 数据
    $info = self::GetInfo([$fid, $uid]);
    foreach($list as $k=>$v) {
      $list[$k]['is_new'] = $v['is_new']&&in_array($uid, json_decode($v['is_new']))?false:true;
      if($v['format']!==0) $list[$k]['content'] = json_decode($v['content'], true);
      // 用户
      if(isset($info[$v['fid']])) {
        $list[$k]['title'] = $info[$v['fid']]['title'];
        $list[$k]['img'] = $info[$v['fid']]['img'];
      }
      // 群组
      if($gid && $v['fid']===0) {
        $list[$k]['title'] = cfg::$service[$gid]['title'];
        $list[$k]['img'] = cfg::$service[$gid]['img'];
      }
    }
    return array_reverse($list);
  }

  /* 用户信息 */
  static function GetInfo(array $uid): array {
    $m = new UserInfo();
    $m->Columns('uid', self::$name, 'img');
    $m->Where('img<>"" AND uid in('.implode(',', $uid).')');
    $all = $m->Find();
    // 数据
    $list = [];
    foreach($all as $v) {
      $list[$v['uid']]['title'] = $v[self::$name];
      $list[$v['uid']]['img'] = sData::Img($v['img']);
    }
    return $list;
  }

  /* 搜索联系人 */
  static function SeaUser(int $uid, string $key): array {
    // 查询
    $m = new UserInfo();
    $m->Table('user_info AS a');
    $m->LeftJoin('user AS b', 'a.uid=b.id');
    $m->Columns('a.uid', 'a.'.self::$name.' as title', 'a.img');
    $m->Where('a.'.self::$name.' like ? AND a.uid<>? AND b.status=1', '%'.$key.'%', $uid);
    $m->Limit(0,10);
    $list = $m->Find();
    foreach($list as $k=>$v){
      $list[$k]['gid'] = 0;
      $list[$k]['fid'] = $v['uid'];
      $list[$k]['uid'] = $uid;
      $list[$k]['img'] = sData::Img($v['img']);
    }
    // Ai助理
    if(!$list) $list = array_merge([
      ['gid'=>1, 'fid'=>0, 'uid'=>1, 'title'=>cfg::$service[1]['title'], 'img'=>cfg::$service[1]['img']],
    ], $list);
    return $list;
  }

  /* 阅读 */
	static function Read($uid, $ids): bool {
    $m = new UserMsg();
    $m->Columns('id', 'is_new');
    $m->Where('id in('.implode(',', $ids).')');
    $all = $m->Find();
    foreach($all as $v) {
      $arr = $v['is_new']?json_decode($v['is_new']):[];
      if(!in_array($uid, $arr)) {
        $arr[] = $uid;
        // 更新
        $m->Set(['is_new'=>json_encode($arr), 'utime'=>time()]);
        $m->Where('id=?', $v['id']);
        $m->Update();
      }
    }
    return true;
  }

  /* 清空 */
  static function Del($gid, $fid, $uid): bool {
    $m = new UserMsg();
    $m->Columns('id', 'is_del');
    $m->Where('gid=? AND (fid=? AND uid=? OR fid=? AND uid=?) AND is_del NOT LIKE "%\"'.$uid.'\"%"', $gid, $fid, $uid, $uid, $fid);
    $all = $m->Find();
    foreach($all as $v) {
      $tmp = $v['is_del']?json_decode($v['is_del']):[];
      if(!in_array((string)$uid, $tmp)) $tmp[] = (string)$uid;
      $m = new UserMsg();
      $m->Set(['is_del'=>json_encode($tmp)]);
      $m->Where('id=?', $v['id']);
      $m->Update();
    }
    return true;
  }

}