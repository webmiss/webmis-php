<?php
namespace App\Admin;

use Core\Controller;
use App\Service\TokenAdmin;
use App\Service\Status;
use App\Librarys\Export;
use App\Util\Util;
use App\Model\ErpBaseShop;
use App\Model\ErpBaseOrganization;

/* 店铺管理 */
class Erp_base_shop extends Controller {

  static private $org_name = [];                // 组织
  static private $city_name = [];               // 城市
  static private $class_name = [];              // 分类
  static private $status_name = [];              // 状态
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
    $m = new ErpBaseShop();
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
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data) || empty($page) || empty($limit)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    self::$org_name = self::getOrgName();
    // 查询
    $m = new ErpBaseShop();
    $m->Columns(
      'id', 'fid', 'city', 'class', 'shop_id', 'name', 'sort', 'status', 'creator_name', 'operator_name', 'remark',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime',
    );
    $m->Where($where);
    $m->Order($order?:'utime DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    foreach($list as $k=>$v) {
      $list[$k]['status'] = $v['status']?true:false;
      // 组织
      $org = array_filter(explode(':', $v['fid']));
      $name = '';
      foreach($org as $v) $name .= self::$org_name[$v].' > ';
      $list[$k]['org_name'] = rtrim($name, ' > ');
    }
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>$list]);
  }
  /* 搜索条件 */
  static private function getWhere(array $d): string {
    $where = [];
    // 关键字
    $key = isset($d['key'])?Util::Trim($d['key']):'';
    if($key){
      $arr = [
        'city like "'.$key.'"',
        'class like "'.$key.'"',
        'shop_id like "'.$key.'"',
        'name like "%'.$key.'%"',
        'remark like "%'.$key.'%"',
      ];
      $where[] = '('.implode(' OR ', $arr).')';
    }
    // 城市
    $city = isset($d['city'])&&is_array($d['city'])?$d['city']:[];
    if($city) $where[] = 'city in("'.implode('","', $city).'")';
    // 分类
    $class = isset($d['class'])&&is_array($d['class'])?$d['class']:[];
    if($class) $where[] = 'class in("'.implode('","', $class).'")';
    // 所属
    $fid = isset($d['fid'])&&is_array($d['fid'])?$d['fid']:[];
    if($fid) $where[] = '(fid LIKE "%:'.implode(':%" OR fid LIKE "%:', $fid).':%")';
    // 状态
    $status = isset($d['status'])&&is_array($d['status'])?$d['status']:[];
    if($status) $where[] = 'status in("'.implode('","', $status).'")';
    // 名称
    $name = isset($d['name'])?trim($d['name']):'';
    if($name) $where[] = 'name LIKE "%'.$name.'%"';
    // 制单员
    $creator_name = isset($d['creator_name'])?trim($d['creator_name']):'';
    if($creator_name) $where[] = 'creator_name LIKE "'.$creator_name.'"';
    // 操作员
    $operator_name = isset($d['operator_name'])?trim($d['operator_name']):'';
    if($operator_name) $where[] = 'operator_name LIKE "'.$operator_name.'"';
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
    $msg = TokenAdmin::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)){
      return self::GetJSON(['code'=>4000]);
    }
    // 数据
    $param = [];
    $id = isset($data['id'])&&$data['id']?trim($data['id']):'';
    $param['fid'] = isset($data['fid'])?trim($data['fid']):'';
    $param['sort'] = isset($data['sort'])?$data['sort']:'';
    $param['city'] = isset($data['city'])&&$data['city']?$data['city'][0]:'';
    $param['class'] = isset($data['class'])&&$data['class']?$data['class'][0]:'';
    $param['shop_id'] = isset($data['shop_id'])?trim($data['shop_id']):'';
    $param['name'] = isset($data['name'])?trim($data['name']):'';
    $param['status'] = isset($data['status'])&&$data['status']?1:0;
    $param['remark'] = isset($data['remark'])?trim($data['remark']):'';
    // 验证
    if(!strstr($param['fid'], ':')) return self::GetJSON(['code'=>4000, 'msg'=>'请输入归属事业部']);
    if(!is_numeric($param['sort'])) return self::GetJSON(['code'=>4000, 'msg'=>'请输入排序数字']);
    if(!is_numeric($param['shop_id'])) return self::GetJSON(['code'=>4000, 'msg'=>'请输入店铺ID']);
    if($param['city']=='') return self::GetJSON(['code'=>4000, 'msg'=>'请选择城市']);
    if($param['class']=='') return self::GetJSON(['code'=>4000, 'msg'=>'请选择分类']);
    if(mb_strlen($param['name'])<2 || mb_strlen($param['name'])>32) return self::GetJSON(['code'=>4000, 'msg'=>'请输入名称']);
    // 操作员
    $admin = TokenAdmin::Token($token);
    $param['operator_id'] = $admin->uid;
    $param['operator_name'] = $admin->name;
    $param['utime'] = time();
    // 添加
    if(!$id) {
      $param['ctime'] = time();
      $param['creator_id'] = $admin->uid;
      $param['creator_name'] = $admin->name;
      $m = new ErpBaseShop();
      $m->Values($param);
      if($m->Insert()) {
        return self::GetJSON(['code'=>0]);
      } else {
        return self::GetJSON(['code'=>5000]);
      }
    }
    // 更新
    $m = new ErpBaseShop();
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
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)){
      return self::GetJSON(['code'=>4000]);
    }
    // 模型
    $m = new ErpBaseShop();
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
    $msg = TokenAdmin::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    // 查询
    $m = new ErpBaseShop();
    $m->Columns(
      'id', 'fid', 'city', 'class', 'shop_id', 'name', 'sort', 'status', 'creator_name', 'operator_name', 'remark',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime',
    );
    $m->Where($where);
    $m->Order($order?:'id DESC');
    $list = $m->Find();
    if(!$list) return self::GetJSON(['code'=>4010]);
    // 导出文件
    $admin = TokenAdmin::Token($token);
    self::$export_filename = 'ErpBaseShop_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', 'FID', '城市', '分类', '店铺ID', '店铺名称', '排序', '状态', '创建时间', '更新时间', '制单员', '操作员', '备注'
    ]);
    // 数据
    foreach($list as $k=>$v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        $v['fid'],
        $v['city'],
        $v['class'],
        $v['shop_id'],
        $v['name'],
        $v['sort'],
        $v['status']?self::GetLang('enable'):self::GetLang('disable'),
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
        $v['creator_name'],
        $v['operator_name'],
        $v['remark'],
      ]);
    }
    $html .= Export::ExcelBottom();
    Export::ExcelFileEnd(self::$export_path, self::$export_filename, $html);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['path'=>self::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]]);
  }

  /* 组织架构 */
  static private function getOrgName(): array {
    $m = new ErpBaseOrganization();
    $m->Columns('id', 'name');
    $all = $m->Find();
    $org_name = [];
    foreach($all as $v) $org_name[$v['id']]=$v['name'];
    return $org_name;
  }

  /* 选项 */
  static function Get_select(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    // 城市
    $city_name = [];
    self::$city_name = Status::Shop('city_name');
    foreach(self::$city_name as $k=>$v) $city_name[]=['label'=>$v, 'value'=>$v];
    // 分类
    $class_name = [];
    self::$class_name = Status::Shop('class_name');
    foreach(self::$class_name as $k=>$v) $class_name[]=['label'=>$v, 'value'=>$v];
    // 组织
    self::$org_name = self::getOrgName();
    foreach(self::$org_name as $k=>$v) $org_name[]=['label'=>$v, 'value'=>$k];
    // 状态
    $status_name = [];
    self::$status_name = Status::Shop('status_name');
    foreach(self::$status_name as $k=>$v) $status_name[]=['label'=>$v, 'value'=>$k];
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>[
      'city_name'=> $city_name,
      'class_name'=> $class_name,
      'org_name'=> $org_name,
      'status_name'=> $status_name,
    ]]);
  }

}