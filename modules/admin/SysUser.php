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
use Model\ApiPerm;
use Model\SysRole;
use Model\SysPerm;
use Model\ApiRole;
use Util\Util;

class SysUser extends Base {

  // 状态
  static private $state = ['0'=>'禁用', '1'=>'正常'];
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
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data) || empty($page) || empty($limit)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 条件
    $param = json_decode($data);
    $where = self::getWhere($param);
    // 统计
    $m = new User();
    $m->Columns('count(*) AS num');
    $m->Table('user as a');
    $m->LeftJoin('user_info as b', 'a.id=b.uid');
    $m->Where($where);
    $total = $m->FindFirst();
    // 查询
    $m->Table('user as a');
    $m->LeftJoin('user_info as b', 'a.id=b.uid');
    $m->LeftJoin('sys_perm as c', 'a.id=c.uid');
    $m->LeftJoin('api_perm as d', 'a.id=d.uid');
    $m->Columns(
      'a.id AS uid', 'a.uname', 'a.email', 'a.tel', 'a.state', 'FROM_UNIXTIME(a.rtime) as rtime', 'FROM_UNIXTIME(a.ltime) as ltime', 'FROM_UNIXTIME(a.utime) as utime',
      'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.img', 'b.remark', 'FROM_UNIXTIME(b.birthday, "%Y-%m-%d") as birthday',
      'c.role AS sys_role', 'c.perm AS sys_perm',
      'd.role AS api_role', 'd.perm AS api_perm'
    );
    $m->Where($where);
    $m->Order($order?:'a.id DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    // 数据
    foreach ($list as $key => $val) {
      $list[$key]['state'] = $val['state']?true:false;
      $list[$key]['img'] = Data::Img($val['img']);
      if(!$val['sys_role']) $list[$key]['sys_role']='';
      if(!$val['sys_perm']) $list[$key]['sys_perm']='';
    }
    // 返回
    return self::GetJSON(['code'=>0,'msg'=>'成功','list'=>$list,'total'=>(int)$total['num']]);
  }
  /* 搜索条件 */
  static private function getWhere(object $param): string {
    $where = [];
    // 账号
    $uname = isset($param->uname)?trim($param->uname):'';
    if($uname) $where[] = '(a.uname LIKE "%'.$uname.'%" OR a.tel LIKE "%'.$uname.'%" OR a.email LIKE "%'.$uname.'%")';
    // 昵称
    $nickname = isset($param->nickname)?trim($param->nickname):'';
    if($nickname) $where[] = 'b.nickname LIKE "%'.$nickname.'%"';
    // 姓名
    $name = isset($param->name)?trim($param->name):'';
    if($name) $where[] = 'b.name LIKE "%'.$name.'%"';
    // 部门
    $department = isset($param->department)?trim($param->department):'';
    if($department) $where[] = 'b.department LIKE "%'.$department.'%"';
    // 职务
    $position = isset($param->position)?trim($param->position):'';
    if($position) $where[] = 'b.position LIKE "%'.$position.'%"';
    // 备注
    $remark = isset($param->remark)?trim($param->remark):'';
    if($remark!='') $where[] = 'a.remark like "%'.$remark.'%"';
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
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
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
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
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
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
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
    $m4 = new ApiPerm();
    $m4->Where('uid in('.$ids.')');
    if($m1->Delete() && $m2->Delete() && $m3->Delete() && $m4->Delete()) {
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    } else {
      return self::GetJSON(['code'=>5000,'msg'=>'删除失败!']);
    }
  }

  /* 状态 */
  static function State(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $uid = self::JsonName($json, 'uid');
    $state = self::JsonName($json, 'state');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($uid)){
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 超级管理员
    $tData = AdminToken::Token($token);
    if($uid==1 && $tData->uid!=1){
      return self::GetJSON(['code'=>4000, 'msg'=>'您不是超级管理员!']);
    }
    // 模型
    $state = $state=='1'?'1':'0';
    $m = new User();
    $m->Set(['state'=>$state]);
    $m->Where('id=?', $uid);
    if($m->Update()) {
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    } else {
      return self::GetJSON(['code'=>5000,'msg'=>'更新失败!']);
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
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($type) || empty($uid)){
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 超级管理员
    $tData = AdminToken::Token($token);
    if($uid==1 && $tData->uid!=1){
      return self::GetJSON(['code'=>4000, 'msg'=>'您不是超级管理员!']);
    }
    // 类型
    if($type=='admin' && self::_permSys($uid, $role, $perm)){
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    }else if($type=='api' && self::_permApi($uid, $role, $perm)){
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    }else{
      return self::GetJSON(['code'=>5000,'msg'=>'更新失败!']);
    }
  }
  // 权限-System
  private static function _permSys($uid, $role, $perm) {
    // 数据
    $uData = ['perm'=>$perm, 'role'=>$role, 'utime'=>time()];
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
  // 权限-System
  private static function _permApi($uid, $role, $perm) {
    // 数据
    $uData = ['perm'=>$perm, 'role'=>$role, 'utime'=>time()];
    // 是否存在
    $m = new ApiPerm();
    $m->Columns('uid');
    $m->Where('uid=?', $uid);
    $one = $m->FindFirst();
    if($one){
      $m->Set($uData);
      $m->Update();
    }else{
      $uData['uid'] = $uid;
      $uData['utime'] = time();
      $m->Values($uData);
      $m->Insert();
    }
    // 角色权限
    if(empty($perm)){
      $m1 = new ApiRole();
      $m1->Columns('perm');
      $m1->Where('id=?', $role);
      $data = $m1->FindFirst();
      $perm = isset($data['perm'])?$data['perm']:'';
    }
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
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($uid) || empty($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 数据
    $param = json_decode($data);
    $info = [
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
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
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
    $m->Columns(
      'a.id AS uid', 'a.uname', 'a.email', 'a.tel', 'a.state', 'FROM_UNIXTIME(a.rtime) as rtime', 'FROM_UNIXTIME(a.ltime) as ltime', 'FROM_UNIXTIME(a.utime) as utime',
      'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.img', 'b.remark', 'FROM_UNIXTIME(b.birthday, "%Y-%m-%d") as birthday',
    );
    $m->Where($where);
    $m->Order($order?:'a.id DESC');
    $list = $m->Find();
    // 导入文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'SysUser_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'UID', '账号', '状态', '昵称', '姓名', '性别', '生日', '部门', '职务', '注册时间', '登录时间', '备注'
    ]);
    // 数据
    foreach($list as $k=>$v){
      // 内容
      $html .= Export::ExcelData([
        $v['uid'],
        $v['tel']?:$v['uname']??$v['email'],
        self::$state[$v['state']],
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
    return self::GetJSON(['code'=>0,'msg'=>'成功','path'=>Env::$base_url.self::$export_path, 'filename'=>self::$export_filename]);
  }

}