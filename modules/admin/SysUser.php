<?php
namespace App\Admin;

use Config\Env;
use Service\Base;
use Service\Data;
use Service\AdminToken;
use Library\Safety;
use Library\Redis;
use Library\Export;
use Model\User;
use Model\UserInfo;
use Model\SysRole;
use Model\SysPerm;
use Util\Util;

class SysUser extends Base {

  // 状态
  static private $stateName = ['0'=>'禁用', '1'=>'正常'];
  // 角色类型
  static private $typeName = ['0'=>'职员', '1'=>'开发'];
  // 导出
  static private $export_path = 'upload/tmp/';  //导出-目录
  static private $export_filename = '';         //导出-文件名

  /* 列表 */
	static function List() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    $page = self::JsonName($json, 'page');
    $limit = self::JsonName($json, 'limit');
    $order = self::JsonName($json, 'order');
    // 验证
    self::Print($_SERVER['REQUEST_URI']);
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data) || !is_array($data) || empty($page) || empty($limit)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 条件
    $where = self::getWhere($data);
    // 统计
    $m = new User();
    $m->Columns('count(*) AS total');
    $m->Table('user as a');
    $m->LeftJoin('user_info as b', 'a.id=b.uid');
    $m->LeftJoin('sys_perm as c', 'a.id=c.uid');
    $m->Where($where);
    $total = $m->FindFirst();
    // 查询
    $m->Columns(
      'a.id', 'a.uname', 'a.email', 'a.tel', 'a.state', 'FROM_UNIXTIME(a.rtime) as rtime', 'FROM_UNIXTIME(a.ltime) as ltime', 'FROM_UNIXTIME(a.utime) as utime',
      'b.type', 'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.img', 'b.remark', 'FROM_UNIXTIME(b.birthday, "%Y-%m-%d") as birthday',
      'c.role', 'c.perm',
    );
    $m->Where($where);
    $m->Order($order?:'a.id DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    // 角色
    $m = new SysRole();
    $m->Columns('id', 'name');
    $all = $m->Find();
    $role = [];
    foreach($all as $v) $role[(string)$v['id']]=$v['name'];
    // 数据
    foreach ($list as $k => $v) {
      $list[$k]['state'] = $v['state']?true:false;
      $list[$k]['state'] = $v['state']?true:false;
      $list[$k]['role_name'] = $v['role']?$role[$v['role']]:'';
      $list[$k]['img'] = Data::Img($v['img']);
    }
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'time'=>date('Y/m/d H:i:s'), 'data'=>['total'=>$total, 'list'=>$list]]);
  }
  /* 搜索条件 */
  static private function getWhere(array $d): string {
    $where = [];
    // 关键字
    $key = isset($d['key'])?trim($d['key']):'';
    if($key){
      $arr = [
        'a.id="'.$key.'"',
        'a.uname like "%'.$key.'%"',
        'a.tel like "%'.$key.'%"',
        'a.email like "%'.$key.'%"',
        'b.nickname like "%'.$key.'%"',
        'b.name like "%'.$key.'%"',
        'b.department like "%'.$key.'%"',
        'b.position like "%'.$key.'%"',
        'b.remark like "%'.$key.'%"',
      ];
      $where[] = '('.implode(' OR ', $arr).')';
    }
    // 昵称
    $nickname = isset($d['nickname'])?trim($d['nickname']):'';
    if($nickname!='') $where[] = 'b.nickname like "%'.$nickname.'%"';
    // 部门
    $department = isset($d['department'])?trim($d['department']):'';
    if($department!='') $where[] = 'b.department like "%'.$department.'%"';
    // 职位
    $position = isset($d['position'])?trim($d['position']):'';
    if($position!='') $where[] = 'b.position like "%'.$position.'%"';
    // 姓名
    $name = isset($d['name'])?trim($d['name']):'';
    if($name!='') $where[] = 'b.name like "%'.$name.'%"';
    // 备注
    $remark = isset($d['remark'])?trim($d['remark']):'';
    if($remark!='') $where[] = 'remark like "%'.$remark.'%"';
    return implode(' AND ', $where);
  }

  /* 添加 */
  static function Add() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 数据
    $param = json_decode($data);
    $tel = isset($param->tel)?trim($param->tel):'';
    $passwd = isset($param->passwd)?$param->passwd:Env::$password;
    // 验证
    if(!Safety::IsRight('tel', $tel)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'手机号码有误!']);
    }
    if(!Safety::IsRight('passwd', $passwd)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'密码为6～16位!']);
    }
    // 是否存在
    $m = new User();
    $m->Columns('id');
    $m->Where('tel=?', $tel);
    $user = $m->FindFirst();
    if(!empty($user)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'该用户已存在!']);
    }
    // 用户
    $m1 = new User();
    $m1->Values(['tel'=>$tel, 'password'=>md5($passwd), 'rtime'=>time()]);
    $m1->Insert();
    $uid = $m1->GetID();
    // 用户信息
    $m2 = new UserInfo();
    $m2->Values(['uid'=>$uid]);
    if($m2->Insert()){
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    }else{
      return self::GetJSON(['code'=>5000,'msg'=>'添加失败!']);
    }
  }

  /* 编辑 */
  static function Edit() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $uid = self::JsonName($json, 'uid');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($uid) || empty($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 数据
    $param = json_decode($data);
    $tel = isset($param->tel)?trim($param->tel):'';
    $passwd = isset($param->passwd)?$param->passwd:'';
    // 验证
    if(!Safety::IsRight('tel', $tel)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'手机号码有误!']);
    }
    // 是否存在
    $m = new User();
    $m->Columns('id');
    $m->Where('tel=?', $tel);
    $user = $m->FindFirst();
    if(!empty($user) && $user['id']!=$uid){
      return self::GetJSON(['code'=>4000, 'msg'=>'该用户已存在!']);
    }
    // 模型
    $uData = ['tel'=>$tel];
    if($passwd!='') $uData['password'] = md5($passwd);
    $m->Set($uData);
    $m->Where('id=?', $uid);
    if($m->Update()) {
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    } else {
      return self::GetJSON(['code'=>5000,'msg'=>'更新失败!']);
    }
  }

  /* 删除 */
  static function Del() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 数据
    $param = json_decode($data);
    $ids = implode(',',$param);
    // 模型
    $m1 = new User();
    $m1->Where('id in('.$ids.')');
    $m2 = new UserInfo();
    $m2->Where('uid in('.$ids.')');
    $m3 = new SysPerm();
    $m3->Where('uid in('.$ids.')');
    if($m1->Delete() && $m2->Delete() && $m3->Delete()) {
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    } else {
      return self::GetJSON(['code'=>5000,'msg'=>'删除失败!']);
    }
  }

  /* 权限 */
  static function Perm(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $type = self::JsonName($json, 'type');
    $uid = self::JsonName($json, 'uid');
    $role = self::JsonName($json, 'role');
    $perm = self::JsonName($json, 'perm');
    $brand = self::JsonName($json, 'brand');
    $shop = self::JsonName($json, 'shop');
    $partner = self::JsonName($json, 'partner');
    $partner_in = self::JsonName($json, 'partner_in');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($type) || empty($uid)){
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 超级管理员
    $tData = AdminToken::Token($token);
    if($uid==1 && $tData->uid!=1){
      return self::GetJSON(['code'=>4000, 'msg'=>'您不是超级管理员!']);
    }
    // 类型
    if($type=='admin' && self::_permSys($uid, $role, $perm, $brand, $shop, $partner, $partner_in)){
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    }else{
      return self::GetJSON(['code'=>5000,'msg'=>'更新失败!']);
    }
  }
  // 权限-System
  private static function _permSys($uid, $role, $perm, $brand, $shop, $partner, $partner_in) {
    // 数据
    $uData = ['perm'=>$perm, 'role'=>$role, 'brand'=>$brand, 'shop'=>$shop, 'partner'=>$partner, 'partner_in'=>$partner_in, 'utime'=>time()];
    // 是否存在
    $m = new SysPerm();
    $m->Columns('uid');
    $m->Where('uid=?', $uid);
    $one = $m->FindFirst();
    if($one){
      $m->Set($uData);
      $m->Where('uid=?', $uid);
      $m->Update();
    }else{
      $uData['uid'] = $uid;
      $uData['utime'] = time();
      $m->Values($uData);
      $m->Insert();
    }
    // 角色权限
    if(empty($perm)){
      $m1 = new SysRole();
      $m1->Columns('perm');
      $m1->Where('id=?', $role);
      $data = $m1->FindFirst();
      $perm = isset($data['perm'])?$data['perm']:'';
    }
    // 更新权限
    return self::_setPerm(Env::$admin_token_prefix.'_perm_'.$uid, $perm);
  }
  // 更新权限
  private static function _setPerm(string $key, string $perm): bool {
    $redis = new Redis();
    $redis->Set($key, $perm);
    $redis->Close();
    return true;
  }

  /* 个人信息 */
  static function Info() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $uid = self::JsonName($json, 'uid');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($uid) || empty($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 数据
    $param = json_decode($data);
    $info = [
      'type'=> isset($param->type)?trim($param->type):'',
      'nickname'=> isset($param->nickname)?trim($param->nickname):'',
      'name'=> isset($param->name)?trim($param->name):'',
      'gender'=> isset($param->gender)?trim($param->gender):'',
      'birthday'=> isset($param->birthday)?Util::StrToTime($param->birthday):0,
      'department'=> isset($param->department)?trim($param->department):'',
      'position'=> isset($param->position)?trim($param->position):'',
      'remark'=> isset($param->remark)?trim($param->remark):'',
    ];
    // 模型
    $m = new UserInfo();
    $m->Set($info);
    $m->Where('uid=?', $uid);
    if($m->Update()) {
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    } else {
      return self::GetJSON(['code'=>5000,'msg'=>'更新失败!']);
    }
  }

  /* 导出 */
  static function Export() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    $order = self::JsonName($json, 'order');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 条件
    $param = json_decode($data);
    $where = self::getWhere($param, $token);
    // 查询
    $m = new User();
    $m->Table('user as a');
    $m->LeftJoin('user_info as b', 'a.id=b.uid');
    $m->LeftJoin('sys_perm as c', 'a.id=c.uid');
    $m->Columns(
      'a.id AS uid', 'a.uname', 'a.email', 'a.tel', 'a.state', 'FROM_UNIXTIME(a.rtime) as rtime', 'FROM_UNIXTIME(a.ltime) as ltime', 'FROM_UNIXTIME(a.utime) as utime',
      'b.type', 'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.img', 'b.remark', 'FROM_UNIXTIME(b.birthday, "%Y-%m-%d") as birthday',
      'c.role as sys_role',
    );
    $m->Where($where);
    $m->Order($order?:'a.id DESC');
    $list = $m->Find();
    // 角色
    $m = new SysRole();
    $m->Columns('id', 'name');
    $all = $m->Find();
    $role = [];
    foreach($all as $v) $role[(string)$v['id']]=$v['name'];
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'SysUser_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'UID', '账号', '状态', '角色', '类型', '昵称', '姓名', '性别', '生日', '部门', '职务', '注册时间', '登录时间', '备注'
    ]);
    // 数据
    foreach($list as $k=>$v){
      // 内容
      $html .= Export::ExcelData([
        $v['uid'],
        $v['tel']?:$v['uname']??$v['email'],
        self::$stateName[$v['state']],
        $v['sys_role']?$role[$v['sys_role']]:'',
        self::$typeName[$v['type']],
        $v['nickname'],
        $v['name'],
        $v['gender'],
        $v['birthday'],
        $v['department'],
        $v['position'],
        '&nbsp;'.$v['rtime'],
        '&nbsp;'.$v['ltime'],
        $v['remark'],
      ]);
    }
    $html .= Export::ExcelBottom();
    Export::ExcelFileEnd(self::$export_path, self::$export_filename, $html);
    // 数据
    return self::GetJSON(['code'=>0,'msg'=>'成功','path'=>Env::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]);
  }

  /* 角色列表 */
  static function RoleList() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 数据
    $m = new SysRole();
    $m->Columns('id', 'name');
    $all = $m->Find();
    $list = [];
    foreach($all as $v) $list[]=['label'=>$v['name'], 'value'=>$v['id']];
    return self::GetJSON(['code'=>0,'msg'=>'成功','data'=>$list]);
  }

}