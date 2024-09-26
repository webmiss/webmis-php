<?php
namespace App\Admin;

use Config\Env;
use Service\Base;
use Service\Data;
use Service\AdminToken;
use Library\Safety;
use Library\Export;
use Model\User;
use Model\UserInfo;
use Model\SysRole;
use Model\SysPerm;
use Model\SysMenu;

class SysUser extends Base {

  private static $menus = [];   // 全部菜单
  private static $perms = [];   // 用户权限
  // 状态
  static private $statusName = ['0'=>'禁用', '1'=>'正常'];
  // 类型
  static private $typeName = ['0'=>'用户', '1'=>'开发'];
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
    $m->LeftJoin('sys_role as d', 'c.role=d.id');
    $m->Where($where);
    $total = $m->FindFirst();
    // 查询
    $m->Columns(
      'a.id', 'a.uname', 'a.email', 'a.tel', 'a.status', 'FROM_UNIXTIME(a.rtime) as rtime', 'FROM_UNIXTIME(a.ltime) as ltime', 'FROM_UNIXTIME(a.utime) as utime',
      'b.type', 'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.img', 'b.remark', 'FROM_UNIXTIME(b.birthday, "%Y-%m-%d") as birthday',
      'c.role', 'c.perm',
      'd.name as role_name',
    );
    $m->Where($where);
    $m->Order($order?:'a.id DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    // 数据
    foreach ($list as $k => $v) {
      $list[$k]['status'] = $v['status']?true:false;
      $list[$k]['type_name'] = isset(self::$typeName[$v['type']])?self::$typeName[$v['type']]:'-';
      $list[$k]['role_name'] = $v['role_name']?:($v['perm']?'私有':'-');
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
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data) || !is_array($data)){
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 数据
    $param = [];
    $id = isset($data['id'])&&$data['id']?trim($data['id']):'';
    $param['status'] = isset($data['status'])&&$data['status']?'1':'0';
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
    // 用户名
    $uname = '';
    if(Safety::IsRight('tel', $param['uname'])) $uname='tel';
    elseif(Safety::IsRight('email', $param['uname'])) $uname='email';
    elseif(Safety::IsRight('uname', $param['uname'])) $uname='uname';
    if(!$uname) return self::GetJSON(['code'=>4000, 'msg'=>'请输入用户名、手机号码、邮箱!']);
    // 密码
    if((!$id && !Safety::IsRight('passwd', $param['passwd'])) || ($id && $param['passwd'] && !Safety::IsRight('passwd', $param['passwd']))) {
      return self::GetJSON(['code'=>4000, 'msg'=>'密码为英文字母开头6～16位!']);
    }
    // 添加
    if(!$id) {
      // 是否存在
      $m = new User();
      $m->Columns('id');
      $m->Where($uname.'=?', $param['uname']);
      $one = $m->FindFirst();
      if($one) return self::GetJSON(['code'=>4000, 'msg'=>'该用户已存在!']);
      // 帐号
      $user = ['password'=>md5($param['passwd']), 'status'=>$param['status'], 'rtime'=>time()];
      $user[$uname] = $param['uname'];
      $m1 = new User();
      $m1->Values($user);
      $m1->Insert();
      $id = $m1->GetID();
      if(!$id) return self::GetJSON(['code'=>5000,'msg'=>'添加失败!']);
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
      $m3->Values(['uid'=>$id, 'utime'=>time(), 'role'=>$param['role'], 'perm'=>$param['perm']]);
      // 执行
      if($m2->Insert() && $m3-> Insert()) {
        return self::GetJSON(['code'=>0,'msg'=>'成功']);
      } else {
        return self::GetJSON(['code'=>5000,'msg'=>'添加失败!']);
      }
    } else {
      // 是否存在
      $m = new User();
      $m->Columns('id');
      $m->Where($uname.'=? AND id<>?', $param['uname'], $id);
      $one = $m->FindFirst();
      if($one) return self::GetJSON(['code'=>4000, 'msg'=>'该用户已存在!']);
      // 帐号
      $user = ['status'=>$param['status'], 'utime'=>time()];
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
      $m3->Set(['uid'=>$id, 'utime'=>time(), 'role'=>$param['role'], 'perm'=>$param['perm']]);
      $m3->Where('uid=?', $id);
      // 执行
      if($m1->Update() && $m2->Update() && $m3-> Update()) {
        return self::GetJSON(['code'=>0,'msg'=>'成功']);
      } else {
        return self::GetJSON(['code'=>5000,'msg'=>'添加失败!']);
      }
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
    if(empty($data) || !is_array($data)){
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
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
      return self::GetJSON(['code'=>0,'msg'=>'成功']);
    } else {
      return self::GetJSON(['code'=>5000,'msg'=>'删除失败!']);
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
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 条件
    $where = self::getWhere($data);
    // 统计
    $m = new User();
    $m->Table('user as a');
    $m->LeftJoin('user_info as b', 'a.id=b.uid');
    $m->LeftJoin('sys_perm as c', 'a.id=c.uid');
    $m->LeftJoin('sys_role as d', 'c.role=d.id');
    $m->Columns('count(*) AS total');
    $m->Where($where);
    $t = $m->FindFirst();
    if($t['total']>self::$export_max) return self::GetJSON(['code'=>5000, 'msg'=>'总数不能大于'.self::$export_max]);
    // 查询
    $m->Columns(
      'a.id', 'a.uname', 'a.email', 'a.tel', 'a.status', 'FROM_UNIXTIME(a.rtime) as rtime', 'FROM_UNIXTIME(a.ltime) as ltime', 'FROM_UNIXTIME(a.utime) as utime',
      'b.type', 'b.nickname', 'b.department', 'b.position', 'b.name', 'b.gender', 'b.img', 'b.remark', 'FROM_UNIXTIME(b.birthday, "%Y-%m-%d") as birthday',
      'c.role', 'c.perm',
      'd.name as role_name',
    );
    $m->Where($where);
    $m->Order($order?:'a.id DESC');
    $list = $m->Find();
    if(!$list) return self::GetJSON(['code'=>5000, 'msg'=>'暂无数据!']);
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
        $v['id'],
        $v['tel']?:$v['uname']??$v['email'],
        self::$statusName[$v['status']],
        $v['role_name']?:($v['perm']?'私有':'-'),
        self::$typeName[$v['type']],
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
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'data'=>['path'=>Env::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]]);
  }

  /* 选项 */
  static function GetSelect(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 返回
    return self::GetJSON(['code'=>0,'msg'=>'成功', 'data'=>[
      'type'=> self::getType(),   // 类型
      'role'=> self::getRole(),   // 角色
    ]]);
  }
  /* 类型 */
  private static function getType(): array {
    $list = [];
    foreach(self::$typeName as $k=>$v) $list[]=['label'=> $v, 'value'=> $k];
    return $list;
  }
  /* 角色 */
  private static function getRole(): array {
    $m = new SysRole();
    $m->Columns('id', 'name');
    $m->Where('status=1');
    $all = $m->Find();
    $list = [['label'=> '无', 'value'=> '']];
    foreach($all as $k=>$v) $list[]=['label'=> $v['name'], 'value'=> $v['id']];
    return $list;
  }

  /* 权限菜单 */
  static function GetPerm(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $perm = self::JsonName($json, 'perm');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
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
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'data'=>$list]);
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