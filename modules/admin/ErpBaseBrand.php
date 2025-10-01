<?php
namespace App\Admin;

use Config\Env;
use Service\Base;
use Service\AdminToken;
use Library\Export;
use Data\Status;
use Util\Util;
use Model\ErpBaseBrand as ErpBaseBrandM;

/* 品牌管理 */
class ErpBaseBrand extends Base {

  static private $class_name = [];              // 分类
  static private $status_name = [];             // 状态
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
    $m = new ErpBaseBrandM();
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
    $m = new ErpBaseBrandM();
    $m->Columns(
      'id', 'class', 'name', 'value', 'sort', 'status', 'creator_name', 'operator_name', 'rule', 'remark',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime',
    );
    $m->Where($where);
    $m->Order($order?:'id DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    foreach($list as $k=>$v) {
      $list[$k]['status'] = $v['status']?true:false;
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
        'class like "'.$key.'"',
        'name like "%'.$key.'%"',
        'value like "%'.$key.'%"',
        'remark like "%'.$key.'%"',
      ];
      $where[] = '('.implode(' OR ', $arr).')';
    }
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
    $param['class'] = isset($data['class'])&&$data['class']?$data['class'][0]:'';
    $param['name'] = isset($data['name'])?trim($data['name']):'';
    $param['value'] = isset($data['value'])?trim($data['value']):'';
    $param['status'] = isset($data['status'])&&$data['status']?1:0;
    $param['rule'] = isset($data['rule'])?trim($data['rule']):'';
    $param['remark'] = isset($data['remark'])?trim($data['remark']):'';
    // 验证
    if(!is_numeric($param['sort'])) return self::GetJSON(['code'=>4000, 'msg'=>'请输入排序数字']);
    if($param['class']=='') return self::GetJSON(['code'=>4000, 'msg'=>'请选择分类']);
    if(mb_strlen($param['name'])<2 || mb_strlen($param['name'])>16) return self::GetJSON(['code'=>4000, 'msg'=>'请输入名称']);
    if(mb_strlen($param['value'])<2 || mb_strlen($param['value'])>64) return self::GetJSON(['code'=>4000, 'msg'=>'请输入值']);
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
      $m = new ErpBaseBrandM();
      $m->Values($param);
      if($m->Insert()) {
        return self::GetJSON(['code'=>0]);
      } else {
        return self::GetJSON(['code'=>5000]);
      }
    }
    // 更新
    $m = new ErpBaseBrandM();
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
    $m = new ErpBaseBrandM();
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
    $m = new ErpBaseBrandM();
    $m->Columns(
      'id', 'class', 'name', 'value', 'sort', 'status', 'creator_name', 'operator_name', 'rule', 'remark',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime',
    );
    $m->Where($where);
    $m->Order($order?:'id DESC');
    $list = $m->Find();
    if(!$list) return self::GetJSON(['code'=>4010]);
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'ErpBaseBrand_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', '分类', '品牌', '值', '排序', '状态', '创建时间', '更新时间', '制单员', '操作员', '编码正则', '备注'
    ]);
    // 数据
    foreach($list as $k=>$v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        $v['class'],
        $v['name'],
        $v['value'],
        $v['sort'],
        $v['status']?self::GetLang('enable'):self::GetLang('disable'),
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
        $v['creator_name'],
        $v['operator_name'],
        $v['rule'],
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
    // 分类
    $class_name = [];
    self::$class_name = Status::Brand('class_name');
    foreach(self::$class_name as $k=>$v) $class_name[]=['label'=>$v, 'value'=>$v];
    // 状态
    $status_name = [];
    self::$status_name = Status::Brand('status_name');
    foreach(self::$status_name as $k=>$v) $status_name[]=['label'=>$v, 'value'=>$k];
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>[
      'class_name'=> $class_name,
      'status_name'=> $status_name,
    ]]);
  }

}