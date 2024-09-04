<?php
namespace App\Admin;

use Config\Env;
use Service\Base;
use Service\AdminToken;
use Library\Export;
use Model\SysMenu;

class SysMenus extends Base {

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
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data) || !is_array($data) || empty($page) || empty($limit)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 数据
    $where = self::getWhere($data);
    // 统计
    $m = new SysMenu();
    $m->Columns('count(*) AS total');
    $m->Where($where);
    $total = $m->FindFirst();
    // 查询
    $m->Columns(
      'id', 'fid', 'title', 'en', 'ico', 'sort', 'url', 'controller', 'remark', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime', 'action'
    );
    $m->Where($where);
    $m->Page($page, $limit);
    $m->Order($order?:'id DESC');
    $list = $m->Find();
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'time'=>date('Y/m/d H:i:s'), 'data'=>['total'=>$total, 'list'=>$list]]);
  }
  /* 搜索条件 */
  static private function getWhere(array $d): string {
    $where = [];
    // 时间
    $stime = isset($d['stime'])&&!empty($d['stime'])?trim($d['stime']):date('Y-m-d', strtotime('-1 year'));
    if($stime){
      $start = strtotime($stime.' 00:00:00');
      // $where[] = 'ctime>='.$start;
    }
    $etime = isset($d['etime'])&&!empty($d['etime'])?trim($d['etime']):date('Y-m-d');
    if($etime){
      $end = strtotime($etime.' 23:59:59');
      $where[] = 'ctime<='.$end;
    }
    // 关键字
    $key = isset($d['key'])?trim($d['key']):'';
    if($key){
      $arr = [
        'fid="'.$key.'"',
        'title like "%'.$key.'%"',
        'en like "%'.$key.'%"',
        'ico like "%'.$key.'%"',
        'url like "%'.$key.'%"',
        'controller like "%'.$key.'%"',
      ];
      $where[] = '('.implode(' OR ', $arr).')';
    }
    // 菜单名称
    $title = isset($d['title'])?trim($d['title']):'';
    if($title!='') $where[] = 'title like "%'.$title.'%"';
    // 英文名称
    $en = isset($d['en'])?trim($d['en']):'';
    if($en!='') $where[] = 'en like "%'.$en.'%"';
    // 前端路由
    $url = isset($d['url'])?trim($d['url']):'';
    if($url!='') $where[] = 'url like "%'.$url.'%"';
    // 接口地址
    $controller = isset($d['controller'])?trim($d['controller']):'';
    if($controller!='') $where[] = 'controller like "%'.$controller.'%"';
    // 备注
    $remark = isset($d['remark'])?trim($d['remark']):'';
    if($remark!='') $where[] = 'remark like "%'.$remark.'%"';
    // 返回
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
    if(empty($data)){
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 数据
    $param = json_decode($data);
    $title = isset($param->title)?trim($param->title):'';
    if($title==''){
      return self::GetJSON(['code'=>4000, 'msg'=>'名称不能为空!']);
    }
    // 模型
    $m = new SysMenu();
    $m->Values([
      'fid'=> isset($param->fid)&&!empty($param->fid)?trim($param->fid):0,
      'title'=> $title,
      'en'=> isset($param->en)?trim($param->en):'',
      'url'=> isset($param->url)?trim($param->url):'',
      'ico'=> isset($param->ico)?trim($param->ico):'',
      'sort'=> isset($param->sort)?trim($param->sort):0,
      'controller'=> isset($param->controller)?trim($param->controller):'',
      'ctime'=> time(),
    ]);
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
    $title = isset($param->title)?trim($param->title):'';
    if($title=='') {
      return self::GetJSON(['code'=>4000, 'msg'=>'名称不能为空!']);
    }
    // 模型
    $m = new SysMenu();
    $m->Set([
      'fid'=> isset($param->fid)&&!empty($param->fid)?trim($param->fid):0,
      'title'=> $title,
      'en'=> isset($param->en)?trim($param->en):'',
      'url'=> isset($param->url)?trim($param->url):'',
      'ico'=> isset($param->ico)?trim($param->ico):'',
      'sort'=> isset($param->sort)?trim($param->sort):0,
      'controller'=> isset($param->controller)?trim($param->controller):'',
      'utime'=> time(),
    ]);
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
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 模型
    $m = new SysMenu();
    $m->Where('id=?', $data);
    if($m->Delete()) {
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    } else {
      return self::GetJSON(['code'=>5000,'msg'=>'删除失败!']);
    }
  }

  /* 动作权限 */
  static function Perm() {
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
    // 模型
    $m = new SysMenu();
    $m->Set(['action'=>$data]);
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
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 查询
    $m = new SysMenu();
    $m->Columns('id', 'fid', 'title', 'en', 'ico', 'sort', 'url', 'controller', 'action', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Order('id');
    $list = $m->Find();
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'SysMenus_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', 'FID', '名称', '英文', '图表', 'URL', 'API', '创建时间', '更新时间', '动作菜单'
    ]);
    // 数据
    foreach($list as $k=>$v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        $v['fid'],
        $v['title'],
        $v['en'],
        $v['ico'],
        $v['url'],
        $v['controller'],
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
        $v['action'],
      ]);
    }
    $html .= Export::ExcelBottom();
    Export::ExcelFileEnd(self::$export_path, self::$export_filename, $html);
    // 数据
    return self::GetJSON(['code'=>0,'msg'=>'成功','path'=>Env::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]);
  }

  /* 获取菜单-全部 */
  static function GetMenusAll() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 全部菜单
    self::_getMenus();
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'menus'=>self::_getMenusAll('0')]);
  }
  // 递归菜单
  private static function _getMenusAll(string $fid) {
    $data = [];
    $M = isset(self::$menus[$fid])?self::$menus[$fid]:[];
    foreach($M as $val){
      $id = $val['id'];
      $tmp = ['label'=>$val['title'], 'value'=>$id];
      $menu = self::_getMenusAll($id);
      if(!empty($menu)) $tmp['children'] = $menu;
      $data[] = $tmp;
    }
    return $data;
  }

  /* 获取菜单-权限 */
  static function GetMenusPerm() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 全部菜单
    self::_getMenus();
    // 用户权限
    self::$permAll = AdminToken::getPerm($token);
    // 返回
    $menus = self::_getMenusPerm('0');
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'data'=>$menus]);
  }
  // 递归菜单
  private static function _getMenusPerm(string $fid) {
    $data = [];
    $M = isset(self::$menus[$fid])?self::$menus[$fid]:[];
    foreach($M as $val){
      // 菜单权限
      $id = (string)$val['id'];
      if(!isset(self::$permAll[$id])) continue;
      $perm = self::$permAll[$id];
      // 动作权限
      $action = [];
      $actionArr = [];
      $actionStr = (string)$val['action'];
      if($actionStr != '') $actionArr=json_decode($actionStr, true);
      foreach($actionArr as $v){
        $permVal = (int)$v['perm'];
        if(($perm&$permVal)>0) $action[]=$v;
      }
      // 数据
      $value = ['url'=>$val['url'], 'controller'=>$val['controller'], 'action'=>$action];
      $tmp = ['icon'=>$val['ico'], 'label'=>$val['title'], 'en'=>$val['en'], 'value'=>$value, 'display'=>true, 'show'=>true];
      $menu = self::_getMenusPerm($id);
      if(!empty($menu)) $tmp['children'] = $menu;
      $data[] = $tmp;
    }
    return $data;
  }

  /* 全部菜单 */
  private static function _getMenus() {
    $model = new SysMenu();
    $model->Columns('id', 'fid', 'title', 'en', 'url', 'ico', 'controller', 'action', 'sort', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $model->Order('sort, id');
    $data = $model->Find();
    foreach($data as $val){
      $fid = (string)$val['fid'];
      self::$menus[$fid][] = $val;
    }
  }

}
