<?php
namespace App\Admin;

use Config\Env;
use Service\Base;
use Service\AdminToken;
use Library\Export;
use Library\Jushuitan\Erp;
use Data\Status;
use Util\Util;
use Model\ErpBasePartner as ErpBasePartnerM;

class ErpBasePartner extends Base {

  
  static private $type_name = [];               // 类型
  static private $class_name = [];              // 分类
  private static $status_name = [];             // 状态
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
    $m = new ErpBasePartnerM();
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
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data) || empty($page) || empty($limit)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 条件
    $where = self::getWhere($data);
    // 查询
    $m = new ErpBasePartnerM();
    $m->Columns(
      'id', 'type', 'class', 'wms_co_id', 'name', 'sort', 'status', 'creator_name', 'operator_name', 'remark',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime',
    );
    $m->Where($where);
    $m->Order($order?:'sort DESC, status DESC, name, id DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    self::$type_name = Status::Partner('type_name');
    foreach($list as $k=>$v) {
      $list[$k]['status'] = $v['status']?true:false;
      $list[$k]['type_name'] = self::$type_name[$v['type']];
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
        'type like "'.$key.'"',
        'class like "'.$key.'"',
        'wms_co_id like "'.$key.'"',
        'name like "%'.$key.'%"',
        'remark like "%'.$key.'%"',
      ];
      $where[] = '('.implode(' OR ', $arr).')';
    }
    // 类型
    $type = isset($d['type'])&&is_array($d['type'])?$d['type']:[];
    if($type) $where[] = 'type in("'.implode('","', $type).'")';
    // 分类
    $class = isset($d['class'])&&is_array($d['class'])?$d['class']:[];
    if($class) $where[] = 'class in("'.implode('","', $class).'")';
    // 状态
    $status = isset($d['status'])&&is_array($d['status'])?$d['status']:[];
    if($status) $where[] = 'status in('.implode(',', $status).')';
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
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)){
      return self::GetJSON(['code'=>4000]);
    }
    // 数据
    $param = [];
    $id = isset($data['id'])&&$data['id']?trim($data['id']):'';
    $param['sort'] = isset($data['sort'])?$data['sort']:'';
    $param['type'] = isset($data['type'])&&$data['type']?$data['type'][0]:'';
    $param['class'] = isset($data['class'])&&$data['class']?$data['class'][0]:'';
    $param['wms_co_id'] = isset($data['wms_co_id'])?trim($data['wms_co_id']):'';
    $param['name'] = isset($data['name'])?trim($data['name']):'';
    $param['status'] = isset($data['status'])&&$data['status']?1:0;
    $param['remark'] = isset($data['remark'])?trim($data['remark']):'';
    // 验证
    if(!is_numeric($param['sort'])) return self::GetJSON(['code'=>4000, 'msg'=>'请输入排序数字']);
    if(!is_numeric($param['wms_co_id'])) return self::GetJSON(['code'=>4000, 'msg'=>'请输入分仓ID']);
    if($param['type']=='') return self::GetJSON(['code'=>4000, 'msg'=>'请选择类型']);
    if($param['class']=='') return self::GetJSON(['code'=>4000, 'msg'=>'请选择分类']);
    if(mb_strlen($param['name'])<2 || mb_strlen($param['name'])>32) return self::GetJSON(['code'=>4000, 'msg'=>'请输入名称']);
    // 操作员
    $admin = AdminToken::Token($token);
    $param['operator_id'] = $admin->uid;
    $param['operator_name'] = $admin->name;
    $param['utime'] = time();
    // 添加
    if(!$id) {
      $param['ctime'] = time();
      $param['creator_id'] = $admin->uid;
      $param['creator_name'] = $admin->name;
      $m = new ErpBasePartnerM();
      $m->Values($param);
      if($m->Insert()) {
        return self::GetJSON(['code'=>0]);
      } else {
        return self::GetJSON(['code'=>5000]);
      }
    }
    // 更新
    $m = new ErpBasePartnerM();
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
    $m = new ErpBasePartnerM();
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
    $m = new ErpBasePartnerM();
    $m->Columns(
      'id', 'type', 'class', 'wms_co_id', 'name', 'sort', 'status', 'creator_name', 'operator_name', 'remark',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime',
    );
    $m->Where($where);
    $m->Order($order?:'id DESC');
    $list = $m->Find();
    if(!$list) return self::GetJSON(['code'=>4010]);
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'ErpBasePartner_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', '类型', '分类', '分仓ID', '仓库名称', '排序', '状态', '创建时间', '更新时间', '制单员', '操作员', '备注'
    ]);
    // 数据
    self::$type_name = Status::Partner('type_name');
    foreach($list as $k=>$v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        self::$type_name[$v['type']],
        $v['class'],
        $v['wms_co_id'],
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
    return self::GetJSON(['code'=>0, 'data'=>['path'=>Env::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]]);
  }

  /* 选项 */
  static function GetSelect(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    // 类型
    $type_name = [];
    self::$type_name = Status::Partner('type_name');
    foreach(self::$type_name as $k=>$v) $type_name[]=['label'=>$v, 'value'=>$k];
    // 分类
    $class_name = [];
    self::$class_name = Status::Partner('class_name');
    foreach(self::$class_name as $k=>$v) $class_name[]=['label'=>$v, 'value'=>$v];
    // 状态
    $status_name = [];
    self::$status_name = Status::Partner('status_name');
    foreach(self::$status_name as $k=>$v) $status_name[]=['label'=>$v, 'value'=>$k];
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>[
      'type_name'=> $type_name,
      'class_name'=> $class_name,
      'status_name'=> $status_name,
    ]]);
  }

  /* 同步聚水谭 */
  static function Pull(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    // 分段
    $admin = AdminToken::Token($token);
    $msg = self::getPartner($admin, 1);
    return self::GetJSON(['code'=>0, 'msg'=>$msg]);
  }
  /* 分段 */
  private static function getPartner($admin, int $page=1){
    // 请求
    $res = Erp::GetPartner(['page_index'=> $page]);
    if(is_string($res)) return $res;
    // 数据
    $data = [];
    foreach($res->datas as $v) $data[$v->wms_co_id]=['wms_co_id'=>$v->wms_co_id, 'name'=>$v->name, 'remark'=>$v->remark2];
    $Ids1 = array_keys($data);
    // 是否存在
    $m = new ErpBasePartnerM();
    $m->Columns('wms_co_id');
    $m->Where('wms_co_id in('.implode(',', $Ids1).')');
    $all = $m->Find();
    $Ids2 = [];
    foreach($all as $v) $Ids2[] = $v['wms_co_id'];
    // 差集
    $ids = array_diff($Ids1, $Ids2);
    $list = [];
    foreach($ids as $id){
      $list[] = ['wms_co_id'=>$data[$id]['wms_co_id'], 'name'=>$data[$id]['name'], 'remark'=>$data[$id]['remark'], 'ctime'=>time(), 'utime'=>time(), 'operator_id'=>$admin->uid, 'operator_name'=>$admin->name];
    }
    // 添加
    if($list){
      $m = new ErpBasePartnerM();
      $m->ValuesAll($list);
      $m->Insert();
    }
    // 下一页
    if($res->has_next) self::getPartner($admin, $res->page_index+1);
    return '同步成功';
  }

}