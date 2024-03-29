<?php
namespace App\Admin;

use Config\Env;
use Service\Base;
use Service\AdminToken;
use Library\Export;
use Model\ApiRole as ApiRoleM;
use Model\ApiMenu;

class ApiRole extends Base {

  private static $menus = [];   //全部菜单
  private static $permAll = []; //用户权限
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
    $m = new ApiRoleM();
    $m->Columns('count(*) AS num');
    $m->Where($where);
    $total = $m->FindFirst();
    // 查询
    $m->Columns('id', 'name', 'perm', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where($where);
    $m->Order($order?:'id DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    // 返回
    return self::GetJSON(['code'=>0,'msg'=>'成功','list'=>$list,'total'=>(int)$total['num']]);
  }
  /* 搜索条件 */
  static private function getWhere(object $param): string {
    $where = [];
    // 名称
    $name = isset($param->name)?trim($param->name):'';
    if($name) $where[] = 'name LIKE "%'.$name.'%"';
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
    $name = isset($param->name)?trim($param->name):'';
    if($name=='') {
      return self::GetJSON(['code'=>4000, 'msg'=>'名称不能为空!']);
    }
    // 模型
    $m = new ApiRoleM();
    $m->Values(['name'=> $name, 'ctime'=> time()]);
    if($m->Insert()) {
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    } else {
      return self::GetJSON(['code'=>5000,'msg'=>'添加失败!']);
    }
  }

  /* 编辑 */
  static function Edit() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $id = self::JsonName($json, 'id');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($id) || empty($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 数据
    $param = json_decode($data);
    $name = isset($param->name)?trim($param->name):'';
    if($name=='') {
      return self::GetJSON(['code'=>4000, 'msg'=>'名称不能为空!']);
    }
    // 模型
    $m = new ApiRoleM();
    $m->Set(['name'=>$name, 'utime'=>time()]);
    $m->Where('id=?', $id);
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
    $m = new ApiRoleM();
    $m->Where('id in('.$ids.')');
    if($m->Delete()) {
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
    $id = self::JsonName($json, 'id');
    $perm = self::JsonName($json, 'perm');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($id)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 模型
    $m = new ApiRoleM();
    $m->Set(['perm'=>$perm, 'utime'=>time()]);
    $m->Where('id=?', $id);
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
    $m = new ApiRoleM();
    $m->Columns('id', 'name', 'perm', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where($where);
    $m->Order($order?:'id DESC');
    $list = $m->Find();
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'ApiRole_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', '名称', '创建时间', '更新时间', '权限'
    ]);
    // 数据
    foreach($list as $k=>$v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        $v['name'],
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
        $v['perm'],
      ]);
    }
    $html .= Export::ExcelBottom();
    Export::ExcelFileEnd(self::$export_path, self::$export_filename, $html);
    // 数据
    return self::GetJSON(['code'=>0,'msg'=>'成功','path'=>Env::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]);
  }

  /* 角色-列表 */
  static function RoleList(){
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') {
      return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    }
    // 查询
    $m = new ApiRoleM();
    $m->Columns('id', 'name');
    $data = $m->Find();
    $lists = [['label'=>'无', 'value'=>0]];
    foreach($data as $val) {
      $lists[] = ['label'=>$val['name'], 'value'=>(int)$val['id']];
    }
    return self::GetJSON(['code'=>0,'msg'=>'成功', 'list'=>$lists]);
  }

  /* 权限-列表 */
  static function PermList() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $perm = self::JsonName($json, 'perm');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') {
      return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    }
    // 全部菜单
    $model = new ApiMenu();
    $model->Columns('id', 'fid', 'title', 'url', 'ico', 'controller', 'action');
    $model->Order('sort, id');
    $data = $model->Find();
    foreach($data as $val){
      $fid = (string)$val['fid'];
      self::$menus[$fid][] = $val;
    }
    // 用户权限
    self::$permAll = self::permArr($perm);
    // 返回
    return self::GetJSON(['code'=>0,'msg'=>'成功', 'list'=>self::_getMenu('0')]);
  }
  // 权限-拆分
  private static function permArr(string $perm): array {
    $permAll = [];
    $arr = !empty($perm)?explode(' ',$perm):[];
    foreach($arr as $val){
      $s = explode(':',$val);
      $permAll[$s[0]] = (int)$s[1];
    }
    return $permAll;
  }
  // 递归菜单
  private static function _getMenu(string $fid) {
    $data = [];
    $M = isset(self::$menus[$fid])?self::$menus[$fid]:[];
    foreach($M as $val){
      // 菜单权限
      $id = (string)$val['id'];
      $perm = isset(self::$permAll[$id])?self::$permAll[$id]:0;
      // 动作权限
      $action = [];
      $actionArr = [];
      $actionStr = (string)$val['action'];
      if($actionStr != '') $actionArr=json_decode($actionStr, true);
      foreach($actionArr as $v){
        $permVal = (int)$v['perm'];
        $checked = ($perm&$permVal)>0?true:false;
        $action[]=[
          'id'=> $val['id'].'_'.$v['perm'],
          'label'=> $v['name'],
          'checked'=> $checked,
          'perm'=> $v['perm'],
        ];
      }
      // 数据
      $checked = isset(self::$permAll[$id])?true:false;
      $tmp = ['id'=>$val['id'], 'label'=>$val['title'], 'checked'=>$checked];
      if($val['fid']==0) $tmp['show'] = true;
      // children
      $menu = self::_getMenu($id);
      if(!empty($menu)) $tmp['children'] = $menu;
      else if(!empty($action)){
        $tmp['action'] = true;
        $tmp['children'] = $action;
      }
      $data[] = $tmp;
    }
    return $data;
  }

}