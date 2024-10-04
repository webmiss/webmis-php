<?php
namespace App\Admin;

use Config\Env;
use Service\Base;
use Service\AdminToken;
use Library\Export;
use Model\SysRole as SysRoleM;
use Model\SysMenu;

class SysRole extends Base {

  private static $menus = [];   // 全部菜单
  private static $perms = [];   // 用户权限
  // 导出
  static private $export_max = 500000;          //导出-最大数
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
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data) || empty($page) || empty($limit)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    // 统计
    $m = new SysRoleM();
    $m->Columns('count(*) AS total');
    $m->Where($where);
    $total = $m->FindFirst();
    // 查询
    $m->Columns('id', 'name', 'status', 'perm', 'remark', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where($where);
    $m->Order($order?:'id DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    foreach($list as $k=>$v) {
      $list[$k]['status'] = $v['status']?true:false;
    }
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>['total'=>$total, 'list'=>$list]]);
  }
  /* 搜索条件 */
  static private function getWhere(array $d): string {
    $where = [];
    // 关键字
    $key = isset($d['key'])?trim($d['key']):'';
    if($key){
      $arr = [
        'name like "%'.$key.'%"',
        'remark like "%'.$key.'%"',
      ];
      $where[] = '('.implode(' OR ', $arr).')';
    }
    // 名称
    $name = isset($d['name'])?trim($d['name']):'';
    if($name) $where[] = 'name LIKE "%'.$name.'%"';
    // 备注
    $remark = isset($d['remark'])?trim($d['remark']):'';
    if($remark!='') $where[] = 'remark like "%'.$remark.'%"';
    // 结果
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
    $param['name'] = isset($data['name'])?trim($data['name']):'';
    if(mb_strlen($param['name'])<2 || mb_strlen($param['name'])>16) return self::GetJSON(['code'=>4000, 'msg'=>self::GetLang('sys_role_name', 2, 16)]);
    $param['status'] = isset($data['status'])&&$data['status']?1:0;
    $param['remark'] = isset($data['remark'])&&$data['remark']?trim($data['remark']):'';
    $param['perm'] = isset($data['perm'])&&$data['perm']?trim($data['perm']):'';
    // 添加
    if(!$id) {
      $param['ctime'] = time();
      $param['utime'] = time();
      $m = new SysRoleM();
      $m->Values($param);
      if($m->Insert()) {
        return self::GetJSON(['code'=>0]);
      } else {
        return self::GetJSON(['code'=>5000]);
      }
    }
    // 更新
    $param['utime'] = time();
    $m = new SysRoleM();
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
    $m = new SysRoleM();
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
    // 统计
    $m = new SysRoleM();
    $m->Columns('count(*) AS total');
    $m->Where($where);
    $t = $m->FindFirst();
    if($t['total']>self::$export_max) return self::GetJSON(['code'=>5000, 'msg'=>self::GetLang('export_limit', self::$export_max)]);
    // 查询
    $m = new SysRoleM();
    $m->Columns('id', 'name', 'status', 'remark', 'perm', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where($where);
    $m->Order($order?:'id DESC');
    $list = $m->Find();
    if(!$list) return self::GetJSON(['code'=>4010]);
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'SysRole_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', '名称', '状态', '权限值', '备注'
    ]);
    // 数据
    foreach($list as $k=>$v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        $v['name'],
        $v['status']?self::GetLang('enable'):self::GetLang('disable'),
        $v['perm'],
        $v['remark'],
      ]);
    }
    $html .= Export::ExcelBottom();
    Export::ExcelFileEnd(self::$export_path, self::$export_filename, $html);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['path'=>Env::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]]);
  }

  /* 权限菜单 */
  static function GetPerm(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $perm = self::JsonName($json, 'perm');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    // 用户权限
    self::$perms = self::permArr($perm);
    // 语言
    $lang = isset($_GET['lang'])&&$_GET['lang']?trim($_GET['lang']):'';
    // 全部菜单
    $m = new SysMenu();
    $m->Columns('id', 'fid', 'title', 'action', $lang);
    $m->Order('sort, id');
    $data = $m->Find();
    foreach($data as $val){
      $fid = (string)$val['fid'];
      self::$menus[$fid][] = $val;
    }
    // 数据
    $list = self::_getMenu('0', $lang);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>$list]);
  }
  // 权限拆分
  private static function permArr(string $perm): array {
    $list = [];
    $arr = !empty($perm)?explode(' ',$perm):[];
    foreach($arr as $val){
      $s = explode(':',$val);
      $list[$s[0]] = (int)$s[1];
    }
    return $list;
  }
  // 递归菜单
  private static function _getMenu(string $fid, string $lang=''): array {
    $data = [];
    $m = isset(self::$menus[$fid])?self::$menus[$fid]:[];
    foreach($m as $v) {
      // 菜单信息
      $id = (string)$v['id'];
      $tmp = ['label'=>$lang?$v[$lang]:$v['title'], 'value'=>$id.':0', 'checked'=>isset(self::$perms[$id])];
      $menu = self::_getMenu($id, $lang);
      // 动作菜单
      $action = $v['action']?json_decode($v['action'], true):[];
      // 下级
      if($menu){
        $tmp['children'] = $menu;
      }elseif($action){
        $list = [];
        foreach($action as $a) {
          $perm = isset(self::$perms[$id])?self::$perms[$id]:0;
          $list[] = ['label'=>$a['name'].'( '.$a['action'].' )', 'value'=>$id.':'.$a['perm'], 'checked'=>($perm&$a['perm'])>0?true:false];
        }
        $tmp['children'] = $list;
      }
      $data[] = $tmp;
    }
    return $data;
  }

}