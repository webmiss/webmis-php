<?php
namespace App\Admin;

use Service\Base;
use Service\Data;
use Service\AdminToken;
use Data\Partner;
use Util\Util;
use Model\ErpPurchaseStock;
use Model\ErpPurchaseIn;
use Model\ErpPurchaseOut;
use Model\ErpPurchaseAllocate;
use Model\ErpOrderShow;

/* 控制台 */
class Index extends Base {

  private static $partner = [];       // 主仓
  // 默认值数据
  private static $default_data = [
    ['label'=>'T1', 'value'=>0, 'cost_amount'=>0, 'sale_amount'=>0],
    ['label'=>'T2', 'value'=>0, 'cost_amount'=>0, 'sale_amount'=>0],
    ['label'=>'T3', 'value'=>0, 'cost_amount'=>0, 'sale_amount'=>0],
  ];

  /* 首页 */
  static function Index() {
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'PHP Admin']);
  }

  /* 选项 */
  static function GetSelect(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    // 仓库
    self::$partner = Partner::GetList(['type=0', 'status=1']);
    $partner_name = [];
    foreach(self::$partner as $k=>$v){
      $partner_name[] = ['label'=>$v['name'], 'value'=>$k];
    }
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>[
      'partner_name'=> $partner_name,
    ]]);
  }

  /* 报表-库存 */
  static function Stock(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 仓库
    self::$partner = Partner::GetList(['type=0', 'status=1']);
    // 字段
    $columns = [];
    foreach(self::$partner as $k=>$v) {
      $columns[] = 'sum(CASE WHEN wms_co_id='.$k.' THEN num ELSE 0 END) as "num_'.$k.'"';
    }
    // 查询
    $m = new ErpPurchaseStock();
    $m->Columns(...$columns);
    $m->Where('num>0');
    $all = $m->FindFirst();
    // 数据
    $list = [];
    foreach(self::$partner as $k=>$v){
      $list[] = ['label'=>$v['name'], 'value'=>(int)$all['num_'.$k], 'wms_co_id'=>$k];
    }
    $list = Util::AarraySort($list, 'value');
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'time'=> date('Y/m/d H:i:s'), 'data'=>$list]);
  }

  /* 报表-列表 */
  static function List(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $stime = self::JsonName($json, 'stime');
    $etime = self::JsonName($json, 'etime');
    $wms_co_id = self::JsonName($json, 'wms_co_id');
    $type = isset($_GET['type'])?trim($_GET['type']):'';
    self::Print('type', $type);
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($stime) || empty($etime) || !is_array($wms_co_id)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 仓库
    self::$partner = Partner::GetList(['type=0', 'status=1']);
    $wms_co_id = !empty($wms_co_id)?$wms_co_id:array_keys(self::$partner);
    // 日期
    $d1 = new \DateTime($stime);
    $d2 = new \DateTime($etime);
    $res = $d1->diff($d2);
    $day = $res->days+1;
    // 本期
    $now = self::getData($type, $stime, $etime, $wms_co_id);
    // 上期
    $o_stime = date('Y-m-d', strtotime('-'.$day.' days', strtotime($stime)));
    $o_etime = date('Y-m-d', strtotime('-1 days', strtotime($etime)));
    $old = self::getData($type, $o_stime, $o_etime, $wms_co_id);
    // 统计
    $total_now = self::getTotal($type, $now);
    $total_old = self::getTotal($type, $old);
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'time'=> date('Y/m/d H:i:s'), 'data'=>[
      'stime'=> $stime,
      'etime'=> $etime,
      'total_now'=> $total_now,
      'total_old'=> $total_old,
      'list'=> $now,
    ]]);
  }
  /* 统计 */
  private static function getTotal(string $type, array $list): array {
    // 默认值
    $total = [
      $type.'_num'=> 0,
      $type.'_cost_amount'=> 0.00,
      $type.'_sale_amount'=> 0.00,
    ];
    // 数据
    foreach($list as $v) {
      $total[$type.'_num'] += $v['value'];
      $total[$type.'_cost_amount'] += $v['cost_amount'];
      $total[$type.'_sale_amount'] += $v['sale_amount'];
    }
    // 格式化
    foreach($total as $k=>$v) {
      $total[$k] = round($v, 2);
    }
    return $total;
  }
  /* 数据 */
  private static function getData(string $type, string $stime, string $etime, array $wms_co_id=[]): array {
    // 时段
    $times = Data::getTimes($stime, $etime);
    // 分区
    $pname = Data::PartitionName(strtotime($stime.' 00:00:00'), strtotime($etime.' 23:59:59'));
    // 图表
    if($type==='in') return self::getDataIn($times, $wms_co_id)?:self::$default_data;
    elseif($type==='out') return self::getDataOut($times, $wms_co_id)?:self::$default_data;
    elseif($type==='allocate_out') return self::getDataAllocateOut($times, $wms_co_id)?:self::$default_data;
    elseif($type==='allocate_in') return self::getDataAllocateIn($times, $wms_co_id)?:self::$default_data;
    elseif($type==='order') return self::getDataOrder($times, $wms_co_id, $pname)?:self::$default_data;
    elseif($type==='refund') return self::getDataRefund($times, $wms_co_id, $pname)?:self::$default_data;
    return self::$default_data;
  }
  /* 数据-入库 */
  private static function getDataIn(array $times, array $wms_co_id=[]): array {
    // 字段
    $columns = [];
    foreach($times as $k=>$v){
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN cost_price ELSE 0 END) as "cost_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN sale_price ELSE 0 END) as "sale_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN num ELSE 0 END) as "num_'.$k.'"';
    }
    $m = new ErpPurchaseIn();
    $m->Columns(...$columns);
    $m->Where('status="2" AND wms_co_id in('.implode(',', $wms_co_id).')');
    $all = $m->FindFirst();
    // 数据
    $data = [];
    foreach($times as $k=>$v){
      if($all['num_'.$k]>0) $data[] = ['label'=>is_numeric($k)?$k.'时':$k, 'value'=>(int)$all['num_'.$k], 'cost_amount'=>$all['cost_amount_'.$k], 'sale_amount'=>$all['sale_amount_'.$k]];
    }
    return $data;
  }
  /* 数据-采退 */
  private static function getDataOut(array $times, array $wms_co_id=[]): array {
    // 字段
    $columns = [];
    foreach($times as $k=>$v){
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN cost_price ELSE 0 END) as "cost_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN sale_price ELSE 0 END) as "sale_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN num ELSE 0 END) as "num_'.$k.'"';
    }
    $m = new ErpPurchaseOut();
    $m->Columns(...$columns);
    $m->Where('status="2" AND wms_co_id in('.implode(',', $wms_co_id).')');
    $all = $m->FindFirst();
    // 数据
    $data = [];
    foreach($times as $k=>$v){
      if($all['num_'.$k]>0) $data[] = ['label'=>is_numeric($k)?$k.'时':$k, 'value'=>(int)$all['num_'.$k], 'cost_amount'=>$all['cost_amount_'.$k], 'sale_amount'=>$all['sale_amount_'.$k]];
    }
    return $data;
  }
  /* 数据-调拨出 */
  private static function getDataAllocateOut(array $times, array $wms_co_id=[]): array {
    // 字段
    $columns = [];
    foreach($times as $k=>$v){
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN sale_price ELSE 0 END) as "sale_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN num ELSE 0 END) as "num_'.$k.'"';
    }
    $m = new ErpPurchaseAllocate();
    $m->Columns(...$columns);
    $m->Where('status="2" AND go_co_id in('.implode(',', $wms_co_id).')');
    $all = $m->FindFirst();
    // 数据
    $data = [];
    foreach($times as $k=>$v){
      if($all['num_'.$k]>0) $data[] = ['label'=>is_numeric($k)?$k.'时':$k, 'value'=>(int)$all['num_'.$k], 'cost_amount'=>'0.00', 'sale_amount'=>$all['sale_amount_'.$k]];
    }
    return $data;
  }
  /* 数据-调拨入 */
  private static function getDataAllocateIn(array $times, array $wms_co_id=[]): array {
    // 字段
    $columns = [];
    foreach($times as $k=>$v){
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN sale_price ELSE 0 END) as "sale_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN num ELSE 0 END) as "num_'.$k.'"';
    }
    $m = new ErpPurchaseAllocate();
    $m->Columns(...$columns);
    $m->Where('status="2" AND link_co_id in('.implode(',', $wms_co_id).')');
    $all = $m->FindFirst();
    // 数据
    $data = [];
    foreach($times as $k=>$v){
      if($all['num_'.$k]>0){
        $data[] = ['label'=>is_numeric($k)?$k.'时':$k, 'value'=>(int)$all['num_'.$k], 'cost_amount'=>'0.00', 'sale_amount'=>$all['sale_amount_'.$k]];
      }
    }
    return $data;
  }
  /* 数据-销售出仓 */
  private static function getDataOrder(array $times, array $wms_co_id=[], string $pname=''): array {
    // 客服仓
    if(!$wms_co_id) return [];
    // 字段
    $columns = [];
    foreach($times as $k=>$v){
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN cost_price*num ELSE 0 END) as "cost_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN sale_price*num ELSE 0 END) as "sale_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN num ELSE 0 END) as "num_'.$k.'"';
    }
    $m = new ErpOrderShow();
    if($pname) $m->Partition($pname);
    $m->Columns(...$columns);
    $m->Where('type in("3", "5") AND category!="礼品"');
    $all = $m->FindFirst();
    // 数据
    $data = [];
    foreach($times as $k=>$v){
      if($all['num_'.$k]>0) $data[] = ['label'=>is_numeric($k)?$k.'时':$k, 'value'=>(int)$all['num_'.$k], 'cost_amount'=>$all['cost_amount_'.$k], 'sale_amount'=>$all['sale_amount_'.$k]];
    }
    return $data;
  }
  /* 数据-售后 */
  private static function getDataRefund(array $times, array $wms_co_id=[], string $pname=''): array {
    // 客服仓
    if(!$wms_co_id) return [];
    // 字段
    $columns = [];
    foreach($times as $k=>$v){
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN cost_price*num ELSE 0 END) as "cost_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN sale_price*num ELSE 0 END) as "sale_amount_'.$k.'"';
      $columns[] = 'sum(CASE WHEN ctime>='.$v[0].' AND ctime<='.$v[1].' THEN num ELSE 0 END) as "num_'.$k.'"';
    }
    $m = new ErpOrderShow();
    if($pname) $m->Partition($pname);
    $m->Columns(...$columns);
    $m->Where('type in("4", "6") AND category!="礼品"');
    $all = $m->FindFirst();
    // 数据
    $data = [];
    foreach($times as $k=>$v){
      if($all['num_'.$k]>0) $data[] = ['label'=>is_numeric($k)?$k.'时':$k, 'value'=>(int)$all['num_'.$k], 'cost_amount'=>$all['cost_amount_'.$k], 'sale_amount'=>$all['sale_amount_'.$k]];
    }
    return $data;
  }

}
