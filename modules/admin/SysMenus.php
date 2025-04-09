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
  static private $export_path = 'upload/tmp/';  // 目录
  static private $export_filename = '';         // 文件名

  /* 统计 */
  static function Total(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    // 统计
    $m = new SysMenu();
    $m->Columns('count(*) AS total');
    $m->Where($where);
    $one = $m->FindFirst();
    $total = [
      'total'=> $one?(int)$one['total']:0,
    ];
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>$total]);
  }

  /* 列表 */
	static function List(): string {
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
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    // 查询
    $m = new SysMenu();
    $m->Columns(
      'id', 'fid', 'title', 'en', 'ico', 'sort', 'url', 'controller', 'status', 'remark', 'action',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime',
      'en_US', 'zh_CN'
    );
    $m->Where($where);
    $m->Page($page, $limit);
    $m->Order($order?:'fid DESC', 'sort');
    $list = $m->Find();
    foreach($list as $k=>$v) {
      $list[$k]['status'] = $v['status']?true:false;
      $list[$k]['action'] = $v['action']?json_decode($v['action']):[];
    }
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>$list]);
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

  /* 添加、更新 */
  static function Save() {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)){
      return self::GetJSON(['code'=>4000]);
    }
    // 数据
    $param = [];
    $id = isset($data['id'])&&$data['id']?trim($data['id']):'';
    $param['status'] = isset($data['status'])&&$data['status']?'1':'0';
    $param['fid'] = isset($data['fid'])&&$data['fid']?end($data['fid']):0;
    $param['title'] = isset($data['title'])?trim($data['title']):'';
    if(mb_strlen($param['title'])<2 || mb_strlen($param['title'])>16) return self::GetJSON(['code'=>4000, 'msg'=>self::GetLang('sys_menus_name', 2, 16)]);
    $param['en'] = isset($data['en'])?trim($data['en']):'';
    $param['ico'] = isset($data['ico'])?trim($data['ico']):'';
    $param['sort'] = isset($data['sort'])?trim($data['sort']):0;
    $param['url'] = isset($data['url'])?trim($data['url']):'';
    $param['controller'] = isset($data['controller'])?trim($data['controller']):'';
    $param['remark'] = isset($data['remark'])?trim($data['remark']):'';
    $param['action'] = isset($data['action'])?json_encode($data['action']):'';
    $param['en_US'] = isset($data['en_US'])?trim($data['en_US']):'';
    $param['zh_CN'] = isset($data['zh_CN'])?trim($data['zh_CN']):'';
    // 添加
    if(!$id) {
      $param['ctime'] = time();
      $param['utime'] = time();
      $m = new SysMenu();
      $m->Values($param);
      if($m->Insert()) {
        return self::GetJSON(['code'=>0]);
      } else {
        return self::GetJSON(['code'=>5000]);
      }
    }
    // 更新
    $param['utime'] = time();
    $m = new SysMenu();
    $m->Set($param);
    $m->Where('id=?', $id);
    if($m->Update()) {
      return self::GetJSON(['code'=>0]);
    } else {
      return self::GetJSON(['code'=>5000]);
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
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)){
      return self::GetJSON(['code'=>4000]);
    }
    // 模型
    $m = new SysMenu();
    $m->Where('id in('.implode(',', $data).')');
    if($m->Delete()) {
      return self::GetJSON(['code'=>0]);
    } else {
      return self::GetJSON(['code'=>5000]);
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
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    // 查询
    $m = new SysMenu();
    $m->Columns(
      'id', 'fid', 'title', 'en', 'ico', 'sort', 'url', 'controller', 'status', 'action', 'remark',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime',
      'en_US', 'zh_CN'
    );
    $m->Where($where);
    $m->Order($order?:'id DESC');
    $list = $m->Find();
    if(!$list) return self::GetJSON(['code'=>4010]);
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'SysMenus_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', 'FID', '名称', '英文', '图标', 'URL', 'API', '状态', '创建时间', '更新时间', 'English', '简体中文', '动作菜单', '备注'
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
        $v['status']?self::GetLang('enable'):self::GetLang('disable'),
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
        $v['en_US'],
        $v['zh_CN'],
        $v['action'],
        $v['remark'],
      ]);
    }
    $html .= Export::ExcelBottom();
    Export::ExcelFileEnd(self::$export_path, self::$export_filename, $html);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['path'=>Env::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]]);
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
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>self::_getMenusAll('0')]);
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
    return self::GetJSON(['code'=>0, 'data'=>$menus]);
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
      $langs = ['en_US'=>$val['en_US'], 'zh_CN'=>$val['zh_CN']];
      $tmp = ['icon'=>$val['ico'], 'label'=>$val['title'], 'en'=>$val['en'], 'value'=>$value, 'langs'=>$langs];
      $menu = self::_getMenusPerm($id);
      if(!empty($menu)) $tmp['children'] = $menu;
      $data[] = $tmp;
    }
    return $data;
  }
  /* 全部菜单 */
  private static function _getMenus() {
    $model = new SysMenu();
    $model->Columns(
      'id', 'fid', 'title', 'en', 'url', 'ico', 'controller', 'sort', 'status',
      'en_US', 'zh_CN',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime',
      'action', 'remark'
    );
    $model->Order('sort, id');
    $data = $model->Find();
    foreach($data as $val){
      $fid = (string)$val['fid'];
      self::$menus[$fid][] = $val;
    }
  }

}
