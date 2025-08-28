<?php
namespace App\Admin;

use Config\Env;
use Library\Export;
use Model\ErpBaseSupplier as ErpBaseSupplierM;
use Service\AdminToken;
use Service\Base;
use Util\Util;

class ErpBaseSupplier extends Base {

  // 城市
  static private $city_name = ['平洲', '瑞丽', '四会', '缅甸'];
  // 状态
  static private $status_name = ['1'=>'正常', '0'=>'禁用'];
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
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 条件
    $param = $data?$data:[];
    $where = self::getWhere($param);
    // 统计
    $m = new ErpBaseSupplierM();
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
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || empty($page) || empty($limit)) return self::GetJSON(['code'=> 4000]);
    $param = $data?$data:[];
    $where = self::getWhere($param, $token);
    // 统计
    $m = new ErpBaseSupplierM();
    $m->Columns('id', 'supplier_id', 'name', 'status', 'tel', 'city', 'depositbank', 'bankacount', 'acountnumber', 'alipay_id', 'alipay_name', 'remark', 'operator_name', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime', 'FROM_UNIXTIME(btime) as btime');
    $m->Where($where);
    $m->Order($order?''.$order:'utime DESC, id DESC');
    $m->Page($page, $limit);
    $list = $m->Find();
    // 数据
    foreach ($list as $k => $v) {
      $list[$k]['status'] = $v['status']?true:false;
    }
    // 返回
    return self::GetJSON(['code'=> 0, 'time'=> date('Y/m/d H:i:s'), 'data'=>$list]);
  }
  /* 搜索条件 */
  static private function getWhere(array $d): string {
    $where = [];
    // 时间
    $stime = isset($d['stime'])&&!empty($d['stime'])?trim($d['stime']):'';
    if($stime){
      $start = strtotime($stime.' 00:00:00');
      $where[] = 'utime>='.$start;
    }
    $etime = isset($d['etime'])&&!empty($d['etime'])?trim($d['etime']):date('Y-m-d');
    if($etime){
      $end = strtotime($etime.' 23:59:59');
      $where[] = 'utime<='.$end;
    }
    // 关键字
    $key = isset($d['key'])?Util::Trim($d['key']):'';
    if($key){
      $arr = [
        'name like "%'.$key.'%"',
        'tel like "%'.$key.'%"',
        'depositbank like "%'.$key.'%"',
        'bankacount like "%'.$key.'%"',
        'acountnumber like "%'.$key.'%"',
        'alipay_id like "%'.$key.'%"',
        'alipay_name like "%'.$key.'%"',
        'remark like "%'.$key.'%"',
      ];
      $where[] = '('.implode(' OR ', $arr).')';
    }
    // 区域
    $city = isset($d['city'])&&!empty($d['city'])?$d['city']:[];
    if($city) $where[] = 'city in("'.implode('","', $city).'")';
    // 状态
    $status = isset($d['status'])&&!empty($d['status'])?$d['status'][0]:'';
    if($status!='') $where[] = 'status='.$status;
    // 是否收款信息
    $istrue = isset($d['istrue'])&&!empty($d['istrue'])?$d['istrue'][0]:'';
    if($istrue!=''){
      if($istrue=='1') $where[] = '(acountnumber<>"" OR alipay_id<>"")';
      if($istrue=='2') $where[] = '(acountnumber="" AND alipay_id="")';
    }
    // 供应商编码
    $supplier_id = isset($d['supplier_id'])?trim($d['supplier_id']):'';
    if($supplier_id){
      $arr = explode(' ', $supplier_id);
      $where[] = 'supplier_id in('.implode(',', $arr).')';
    }
    // 供应商名称
    $name = isset($d['name'])?trim($d['name']):'';
    if($name) $where[] = 'name like "%'.$name.'%"';
    // 手机号码
    $tel = isset($d['tel'])?trim($d['tel']):'';
    if($tel) $where[] = 'tel like "%'.$tel.'%"';
    // 开户银行
    $depositbank = isset($d['depositbank'])?trim($d['depositbank']):'';
    if($depositbank) $where[] = 'depositbank like "%'.$depositbank.'%"';
    // 账户名称
    $bankacount = isset($d['bankacount'])?trim($d['bankacount']):'';
    if($bankacount) $where[] = 'bankacount like "%'.$bankacount.'%"';
    // 银行账号
    $acountnumber = isset($d['acountnumber'])?trim($d['acountnumber']):'';
    if($acountnumber) $where[] = 'acountnumber like "%'.$acountnumber.'%"';
    // 支付宝账号
    $alipay_id = isset($d['alipay_id'])?trim($d['alipay_id']):'';
    if($alipay_id) $where[] = 'alipay_id like "%'.$alipay_id.'%"';
    // 支付宝姓名
    $alipay_name = isset($d['alipay_name'])?trim($d['alipay_name']):'';
    if($alipay_name) $where[] = 'alipay_name like "%'.$alipay_name.'%"';
    // 备注
    $remark = isset($d['remark'])?trim($d['remark']):'';
    if($remark!='') $where[] = 'remark like "%'.$remark.'%"';
    // 返回
    return implode(' AND ', $where);
  }

  /* 保存 */
  static function Save(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if ($msg != '') return self::GetJSON(['code'=>4001]);
    if (empty($data) || !is_array($data)) return self::GetJSON(['code'=>4000]);
    // 数据
    $param = [];
    $id = isset($data['id'])?Util::Trim($data['id']):'';
    $param['name'] = isset($data['name'])?Util::Trim($data['name']):'';
    if(!$param['name']) return self::GetJSON(['code' => 4000]);
    $param['supplier_id'] = isset($data['supplier_id'])&&$data['supplier_id']?Util::Trim($data['supplier_id']):'0';
    $param['status'] = isset($data['status'])&&$data['status']?1:0;
    $param['tel'] = isset($data['tel'])?Util::Trim($data['tel']):'';
    $param['city'] = isset($data['city'])&&is_array($data['city'])?$data['city'][0]:'';
    $param['depositbank'] = isset($data['depositbank'])?Util::Trim($data['depositbank']):'';
    $param['bankacount'] = isset($data['bankacount'])?Util::Trim($data['bankacount']):'';
    $param['acountnumber'] = isset($data['acountnumber'])?Util::Trim($data['acountnumber']):'';
    $param['alipay_id'] = isset($data['alipay_id'])?Util::Trim($data['alipay_id']):'';
    $param['alipay_name'] = isset($data['alipay_name'])?Util::Trim($data['alipay_name']):'';
    $param['remark'] = isset($data['remark'])?Util::Trim($data['remark']):'';
    $admin = AdminToken::Token($token);
    $param['operator_id'] = $admin->uid;
    $param['operator_name'] = $admin->name;
    $param['utime'] = time();
    // 模型
    $m = new ErpBaseSupplierM();
    if(!$id) {
      // 是否存在
      $m->Columns('id');
      $m->Where('name=?', $param['name']);
      $one = $m->FindFirst();
      if($one) return self::GetJSON(['code'=>4000, 'msg'=>'[ '.$param['name'].' ]已存在!']);
      // 添加
      $param['ctime'] = time();
      $m->Values($param);
      return $m->Insert()?self::GetJSON(['code'=>0]):self::GetJSON(['code'=>5000]);
    }
    // 更新
    $m->Set($param);
    $m->Where('id=?', $id);
    return $m->Update()?self::GetJSON(['code'=>0]):self::GetJSON(['code'=>5000]);
  }

  /* 删除 */
  static function Del(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data)) return self::GetJSON(['code'=> 4000]);
    $ids = implode(',', $data);
    // 模型
    $m = new ErpBaseSupplierM();
    $m->Where('id in('.$ids.')');
    if ($m->Delete()) return self::GetJSON(['code' => 0]);
    else return self::GetJSON(['code' => 5000]);
  }

  /* 导出 */
  static function Export(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    $order = self::JsonName($json, 'order');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data)) return self::GetJSON(['code'=> 4000]);
    // 条件
    $data = $data?$data:[];
    $where = self::getWhere($data);
    // 查询
    $m = new ErpBaseSupplierM();
    $m->Columns('id', 'supplier_id', 'name', 'status', 'tel', 'city', 'depositbank', 'bankacount', 'acountnumber', 'alipay_name', 'alipay_id', 'operator_name', 'remark', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where($where);
    $m->Order($order?:'utime DESC');
    $list = $m->Find();
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'Goods_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', '供应商ID', '供应商名称', '状态', '手机号码', '区域', '开户银行', '账户名称', '银行卡号', '支付宝姓名', '支付宝账号', '创建时间', '更新时间', '操作员', '备注'
    ]);
    // 数据
    foreach($list as $v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        $v['supplier_id'],
        $v['name'],
        self::$status_name[$v['status']],
        $v['tel'],
        $v['city'],
        $v['depositbank'],
        $v['bankacount'],
        $v['acountnumber'],
        $v['alipay_name'],
        $v['alipay_id'],
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
        $v['operator_name'],
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
    // 区域
    $city_name = [];
    foreach(self::$city_name as $v) $city_name[]=['label'=>$v, 'value'=>$v];
    // 状态
    $status_name = [];
    foreach(self::$status_name as $k=>$v) $status_name[]=['label'=>$v, 'value'=>$k];
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['city_name'=> $city_name, 'status_name'=>$status_name]]);
  }

  /* 查询供应商 */
  static function GetInfo(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $name = self::JsonName($json, 'name');
    // 验证权限
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($name)) return self::GetJSON(['code'=> 4000]);
    $data = [];
    $m = new ErpBaseSupplierM();
    $m->Columns('id', 'supplier_id', 'name', 'status', 'tel', 'depositbank', 'bankacount', 'acountnumber', 'alipay_id', 'alipay_name', 'remark', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime', 'FROM_UNIXTIME(btime) as btime');
    $m->Where('status=1 AND name=?', trim($name));
    $data = $m->FindFirst();
    if($data) return self::GetJSON(['code'=>0, 'time'=> date('Y/m/d H:i:s'), 'data'=> $data]);
    else return self::GetJSON(['code'=> 4000, 'msg'=>self::GetLang('supplier_none', trim($name))]);
  }

}
