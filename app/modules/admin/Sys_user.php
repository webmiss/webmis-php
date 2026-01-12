<?php
namespace App\Admin;

use Core\Controller;
use Core\Redis;
use App\Config\Env;
use App\Service\Data;
use App\Service\TokenAdmin;
use App\Librarys\Safety;
use App\Librarys\Export;

use App\Model\User;
use App\Model\UserInfo;
use App\Model\SysMenu;
use App\Model\SysPerm;
use App\Model\SysRole;
use App\Model\ErpBaseBrand;
use App\Model\ErpBaseShop;
use App\Model\ErpBasePartner;

class Sys_user extends Controller {

  private static $menus = [];   // 全部菜单
  private static $perms = [];   // 用户权限
  // 类型
  private static $type_name = [
    '0'=> '用户',
    '1'=> '供应商',
    '2'=> '采购员',
    '3'=> '主管',
    '4'=> '库管',
    '5'=> '客服',
    '9'=> '开发',
  ];
  // 状态
  private static $status_name = ['0'=>'禁用', '1'=>'正常'];
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
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    // 统计
    $m = new User();
    $m->Columns('count(*) AS total');
    $m->Table('user as a');
    $m->LeftJoin('user_info as b', 'a.id=b.uid');
    $m->LeftJoin('sys_perm as c', 'a.id=c.uid');
    $m->LeftJoin('sys_role as d', 'c.role=d.id');
    $m->Where($where);
    $one = $m->FindFirst();
    $total = [
      'total'=> $one?(int)$one['total']:0,
    ];
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>$total]);
  }

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
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data) || empty($page) || empty($limit)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    // 查询
    $m = new User();
    $m->Table('user as a');
    $m->LeftJoin('user_info as b', 'a.id=b.uid');
    $m->LeftJoin('sys_perm as c', 'a.id=c.uid');
    $m->LeftJoin('sys_role as d', 'c.role=d.id');
    $m->Columns(
      'a.id', 'a.uname', 'a.email', 'a.tel', 'a.state', 'FROM_UNIXTIME(a.rtime) as rtime', 'FROM_UNIXTIME(a.ltime) as ltime', 'FROM_UNIXTIME(a.utime) as utime',
      'b.type', 'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.img', 'b.remark', 'FROM_UNIXTIME(b.birthday, "%Y-%m-%d") as birthday',
      'c.role', 'c.perm', 'c.brand', 'c.shop', 'c.partner', 'c.partner_in',
      'd.name as role_name',
    );
    $m->Where($where);
    $m->Order($order?:'a.ltime DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    // 数据
    foreach ($list as $k => $v) {
      $list[$k]['state'] = $v['state']?true:false;
      $list[$k]['type_name'] = isset(self::$type_name[$v['type']])?self::$type_name[$v['type']]:'-';
      $list[$k]['role_name'] = $v['role_name']?:($v['perm']?'私有':'-');
      $list[$k]['img'] = Data::Img($v['img']);
    }
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>$list]);
  }
  /* 搜索条件 */
  static private function getWhere(array $d): string {
    $where = [];
    // 时间
    $stime = isset($d['stime'])?trim($d['stime']):date('Y-m-d');
    if($stime){
      $start = strtotime($stime.' 00:00:00');
      $where[] = 'a.ltime>='.$start;
    }
    $etime = isset($d['etime'])?trim($d['etime']):date('Y-m-d');
    if($etime){
      $end = strtotime($etime.' 23:59:59');
      $where[] = 'a.ltime<='.$end;
    }
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
    // 类型
    $type = isset($d['type'])&&!empty($d['type'])?$d['type']:[];
    if($type) $where[] = 'b.type in('.implode(',', $type).')';
    // 角色
    $role = isset($d['role'])&&!empty($d['role'])?$d['role']:[];
    if($role) $where[] = 'd.id in('.implode(',', $role).')';
    // 状态
    $state = isset($d['state'])&&!empty($d['state'])?$d['state']:[];
    if($state) $where[] = 'a.state in("'.implode('","', $state).'")';
    // 用户名
    $uname = isset($d['uname'])?trim($d['uname']):'';
    if($uname!='') $where[] = '(a.uname="'.$uname.'" OR a.tel="'.$uname.'" OR a.email="'.$uname.'")';
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
    // 结果
    return implode(' AND ', $where);
  }

  /* 添加、更新 */
  static function Save(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)){
      return self::GetJSON(['code'=>4000]);
    }
    // 数据
    $param = [];
    $id = isset($data['id'])&&$data['id']?trim($data['id']):'';
    $param['state'] = isset($data['state'])&&$data['state']?'1':'0';
    $param['uname'] = isset($data['uname'])&&$data['uname']?trim($data['uname']):'';
    $param['passwd'] = isset($data['passwd'])&&$data['passwd']?trim($data['passwd']):'';
    $param['type'] = isset($data['type'])&&$data['type']?$data['type'][0]:0;
    $param['name'] = isset($data['name'])&&$data['name']?trim($data['name']):'';
    $param['nickname'] = isset($data['nickname'])&&$data['nickname']?trim($data['nickname']):'';
    $param['department'] = isset($data['department'])&&$data['department']?trim($data['department']):'';
    $param['position'] = isset($data['position'])&&$data['position']?trim($data['position']):'';
    $param['remark'] = isset($data['remark'])&&$data['remark']?trim($data['remark']):'';
    $param['role'] = isset($data['role'])&&$data['role']?trim($data['role']):'';
    $param['perm'] = isset($data['perm'])&&$data['perm']?trim($data['perm']):'';
    $param['brand'] = isset($data['brand'])&&$data['brand']?trim($data['brand']):'';
    $param['shop'] = isset($data['shop'])?trim($data['shop']):'';
    $param['partner'] = isset($data['partner'])?trim($data['partner']):'';
    $param['partner_in'] = isset($data['partner_in'])?trim($data['partner_in']):'';
    // 用户名
    $uname = '';
    if(Safety::IsRight('tel', $param['uname'])) $uname='tel';
    elseif(Safety::IsRight('email', $param['uname'])) $uname='email';
    elseif(Safety::IsRight('uname', $param['uname'])) $uname='uname';
    if(!$uname) return self::GetJSON(['code'=>4000, 'msg'=>self::GetLang('sys_user_uname')]);
    // 密码
    if((!$id && !Safety::IsRight('passwd', $param['passwd'])) || ($id && $param['passwd'] && !Safety::IsRight('passwd', $param['passwd']))) {
      return self::GetJSON(['code'=>4000, 'msg'=>self::GetLang('sys_user_passwd', 6, 16)]);
    }
    // 添加
    if(!$id) {
      // 是否存在
      $m = new User();
      $m->Columns('id');
      $m->Where($uname.'=?', $param['uname']);
      $one = $m->FindFirst();
      if($one) return self::GetJSON(['code'=>4000, 'msg'=>self::GetLang('sys_user_is_exist')]);
      // 帐号
      $user = ['password'=>md5($param['passwd']), 'state'=>$param['state'], 'rtime'=>time(), 'ltime'=>time()];
      $user[$uname] = $param['uname'];
      $m1 = new User();
      $m1->Values($user);
      $m1->Insert();
      $id = $m1->GetID();
      if(!$id) return self::GetJSON(['code'=>5000]);
      // 基本信息
      $m2 = new UserInfo();
      $m2->Values([
        'uid'=> $id,
        'type'=> $param['type'],
        'utime'=> time(),
        'name'=> $param['name'],
        'nickname'=> $param['nickname'],
        'department'=> $param['department'],
        'position'=> $param['position'],
        'remark'=> $param['remark'],
      ]);
      // 用户权限
      $m3 = new SysPerm();
      $m3->Values(['uid'=>$id, 'utime'=>time(), 'role'=>$param['role'], 'perm'=>$param['perm'], 'brand'=>$param['brand'], 'shop'=>$param['shop'], 'partner'=>$param['partner'], 'partner_in'=>$param['partner_in']]);
      // 执行
      if($m2->Insert() && $m3-> Insert()) {
        return self::GetJSON(['code'=>0]);
      } else {
        return self::GetJSON(['code'=>5000]);
      }
    } else {
      // 是否存在
      $m = new User();
      $m->Columns('id');
      $m->Where($uname.'=? AND id<>?', $param['uname'], $id);
      $one = $m->FindFirst();
      if($one) return self::GetJSON(['code'=>4000, 'msg'=>self::GetLang('sys_user_is_exist')]);
      // 帐号
      $user = ['state'=>$param['state'], 'utime'=>time()];
      if($param['passwd']) $user['password'] = md5($param['passwd']);
      $user[$uname] = $param['uname'];
      $m1 = new User();
      $m1->Set($user);
      $m1->Where('id=?', $id);
      // 基本信息
      $m2 = new UserInfo();
      $m2->Set([
        'type'=> $param['type'],
        'utime'=> time(),
        'name'=> $param['name'],
        'nickname'=> $param['nickname'],
        'department'=> $param['department'],
        'position'=> $param['position'],
        'remark'=> $param['remark'],
      ]);
      $m2->Where('uid=?', $id);
      // 用户权限
      $m3 = new SysPerm();
      $m3->Columns('uid');
      $m3->Where('uid=?', $id);
      $one = $m3->FindFirst();
      $data = ['uid'=>$id, 'utime'=>time(), 'role'=>$param['role'], 'perm'=>$param['perm'], 'brand'=>$param['brand'], 'shop'=>$param['shop'], 'partner'=>$param['partner'], 'partner_in'=>$param['partner_in']];
      if(!$one) {
        $m3->Values($data);
        $m3->Insert();
      }
      $m3->Set($data);
      $m3->Where('uid=?', $id);
      // 执行
      if($m1->Update() && $m2->Update() && $m3->Update()) {
        // 清除Token
        $redis = new Redis();
        $redis->Expire(Env::$admin_token_prefix.'_token_'.$id, 0);
        $redis->Expire(Env::$api_token_prefix.'_token_'.$id, 0);
        $redis->Expire(Env::$supplier_token_prefix.'_token_'.$id, 0);
        $redis->Close();
        // 返回
        return self::GetJSON(['code'=>0]);
      } else {
        return self::GetJSON(['code'=>5000]);
      }
    }
  }

  /* 删除 */
  static function Del(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)){
      return self::GetJSON(['code'=>4000]);
    }
    // 数据
    $ids = implode(',', $data);
    $m1 = new User();
    $m1->Where('id in('.$ids.')');
    $m2 = new UserInfo();
    $m2->Where('uid in('.$ids.')');
    $m3 = new SysPerm();
    $m3->Where('uid in('.$ids.')');
    if($m1->Delete() && $m2->Delete() && $m3->Delete()) {
      return self::GetJSON(['code'=>0]);
    } else {
      return self::GetJSON(['code'=>5000]);
    }
  }

  /* 导出 */
  static function Export(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    $order = self::JsonName($json, 'order');
    // 验证
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    // 查询
    $m = new User();
    $m->Table('user as a');
    $m->LeftJoin('user_info as b', 'a.id=b.uid');
    $m->LeftJoin('sys_perm as c', 'a.id=c.uid');
    $m->LeftJoin('sys_role as d', 'c.role=d.id');
    $m->Columns(
      'a.id', 'a.uname', 'a.email', 'a.tel', 'a.state', 'FROM_UNIXTIME(a.rtime) as rtime', 'FROM_UNIXTIME(a.ltime) as ltime', 'FROM_UNIXTIME(a.utime) as utime',
      'b.type', 'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.img', 'b.remark', 'FROM_UNIXTIME(b.birthday, "%Y-%m-%d") as birthday',
      'c.role', 'c.perm',
      'd.name as role_name',
    );
    $m->Where($where);
    $m->Order($order?:'a.id DESC');
    $list = $m->Find();
    if(!$list) return self::GetJSON(['code'=>4010]);
    // 导出文件
    $admin = TokenAdmin::Token($token);
    self::$export_filename = 'SysUser_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'UID', '账号', '状态', '角色', '类型', '昵称', '姓名', '性别', '生日', '部门', '职务', '注册时间', '登录时间', '备注'
    ]);
    // 数据
    foreach($list as $k=>$v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        $v['tel']?:$v['uname']??$v['email'],
        $v['state']?self::GetLang('enable'):self::GetLang('disable'),
        $v['role_name']?:($v['perm']?'私有':'-'),
        self::$type_name[$v['type']],
        $v['nickname'],
        $v['name'],
        $v['gender']?:'-',
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
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['path'=>self::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]]);
  }

  /* 选项 */
  static function Get_select(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    // 类型
    $type_name = [];
    foreach(self::$type_name as $k=>$v) $type_name[]=['label'=> $v, 'value'=> $k];
    // 角色
    $m = new SysRole();
    $m->Columns('id', 'name');
    $m->Where('status=1');
    $all = $m->Find();
    $role_name = [['label'=> '无', 'value'=> '']];
    foreach($all as $k=>$v) $role_name[]=['label'=> $v['name'], 'value'=> $v['id']];
    // 状态
    $status_name = [];
    foreach(self::$status_name as $k=>$v) $status_name[]=['label'=> $v, 'value'=> $k];
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>[
      'type_name'=> $type_name,
      'role_name'=> $role_name,
      'status_name'=> $status_name,
    ]]);
  }

  /* 权限菜单 */
  static function Get_perm(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $perm = self::JsonName($json, 'perm');
    // 验证
    $msg = TokenAdmin::Verify($token, '');
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

  /* 品牌 */
  static function Get_brand(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $brand = self::JsonName($json, 'brand');
    // 验证
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    $brand = explode(',', $brand);
    // 数据
    $m = new ErpBaseBrand();
    $m->Columns('name', 'value');
    $m->Where('state=1');
    $m->Order('name');
    $all = $m->Find();
    $list = [];
    foreach($all as $v) {
      $list[] = ['label'=>$v['name'], 'value'=>$v['value'], 'checked'=>in_array($v['value'], $brand)];
    }
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>$list]);
  }

  /* 店铺 */
  static function Get_shop(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $shop = self::JsonName($json, 'shop');
    // 验证
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    $shop = explode(',', $shop);
    // 数据
    $m = new ErpBaseShop();
    $m->Columns('name', 'shop_id');
    $m->Where('state=1');
    $m->Order('name');
    $all = $m->Find();
    $list = [];
    foreach($all as $v) {
      $list[] = ['label'=>$v['name'], 'value'=>$v['shop_id'], 'checked'=>in_array($v['shop_id'], $shop)];
    }
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>$list]);
  }

  /* 分仓 */
  static function Get_partner(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $partner = self::JsonName($json, 'partner');
    // 验证
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    $partner = explode(',', $partner);
    // 数据
    $m = new ErpBasePartner();
    $m->Columns('name', 'wms_co_id');
    $m->Where('state=1');
    $m->Order('sort DESC', 'name');
    $all = $m->Find();
    $list = [];
    foreach($all as $v) {
      $list[] = ['label'=>$v['name'], 'value'=>$v['wms_co_id'], 'checked'=>in_array($v['wms_co_id'], $partner)];
    }
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>$list]);
  }

}
