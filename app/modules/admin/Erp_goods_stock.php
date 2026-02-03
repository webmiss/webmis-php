<?php
namespace App\Admin;

use Core\Controller;
use App\Service\TokenAdmin;
use App\Service\Logs;
use App\Service\Goods;
use App\Librarys\Export;
use App\Util\Util;
use App\Task\Stock;

use App\Model\ErpPurchaseStock;
use App\Model\ErpPurchaseAllocateShow;
use App\Model\ErpPurchaseInShow;
use App\Model\ErpPurchaseOutShow;
use App\Model\ErpOrderShow;

use App\Model\ErpBasePartner;
use App\Model\ErpBaseCategory;

/* 商品库存 */
class Erp_goods_stock extends Controller {

  static $category = [];                            // 分类
  static private $partner_name = [];                // 主仓
  // 导出
  static private $export_path = 'upload/tmp/';      // 目录
  static private $export_filename = '';             // 文件名

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
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 条件
    $where = self::getWhere($data, $token);
    // 统计
    $m = new ErpPurchaseStock();
    $m->Columns('count(*) AS total', 'sum(num) AS num');
    $m->Where($where);
    $one = $m->FindFirst();
    $total = [
      'total'=> $one?(int)$one['total']:0,
      'stock'=> $one?(int)$one['num']:0,
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
    // 验证权限
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data) || empty($page) || empty($limit)) return self::GetJSON(['code'=> 4000]);
    $where = self::getWhere($data, $token);
    // 查询
    $m = new ErpPurchaseStock();
    $m->Columns('id', 'sku_id', 'num', 'wms_co_id', 'category', 'owner', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where($where);
    $m->Page($page, $limit);
    $m->Order($order?:'utime DESC');
    $list = $m->Find();
    // 数据
    $sku = [];
    foreach($list as $k=>$v) $sku[$v['sku_id']] = 0;
    // 分区
    $pname = Goods::GoodsInfoAll(array_keys($sku), 'pname', [0, 3]);
    // 入库
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('sku_id IN("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $in = [];
    foreach($all as $v) $in[$v['sku_id']] = $v['status'];
    // 退货
    $m = new ErpPurchaseOutShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('sku_id IN("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $out = [];
    foreach($all as $v) $out[$v['sku_id']] = $v['status'];
    // 调拨
    $m = new ErpPurchaseAllocateShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('sku_id in("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $allocate = [];
    foreach($all as $v) $allocate[$v['sku_id']]=$v['status'];
    // 发货
    $m = new ErpOrderShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('type in("1", "3") AND sku_id in("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $order = [];
    foreach($all as $v) $order[$v['sku_id']]=$v['status'];
    // 售后
    $m = new ErpOrderShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('type in("2", "4") AND sku_id in("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $refund = [];
    foreach($all as $v) $refund[$v['sku_id']]=$v['state'];
    // 整理
    self::$partner_name = ErpBasePartner::GetList();
    foreach($list as $k=>$v) {
      $list[$k]['wms_co_name'] = self::$partner_name[$v['wms_co_id']]['name'];
      $list[$k]['in'] = isset($in[$v['sku_id']])?($in[$v['sku_id']]=='0'?'进行中':'完成'):'-';
      $list[$k]['out'] = isset($out[$v['sku_id']])?($out[$v['sku_id']]=='0'?'进行中':'完成'):'-';
      $list[$k]['allocate'] = isset($allocate[$v['sku_id']])?($allocate[$v['sku_id']]=='0'?'调拨中':'完成'):'-';
      $list[$k]['order'] = isset($order[$v['sku_id']])?($order[$v['sku_id']]=='0'?'进行中':'完成'):'-';
      $list[$k]['refund'] = isset($refund[$v['sku_id']])?($refund[$v['sku_id']]=='0'?'进行中':'完成'):'-';
    }
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>$list]);
  }
  /* 搜索条件 */
  static private function getWhere(array $d, string $token): string {
    $where = [];
    $admin = TokenAdmin::Token($token);
    // 限制-分仓
    if($admin->partner){
      $where[] = 'wms_co_id in('.$admin->partner.')';
    }
    // 时间
    $stime = isset($d['stime'])?trim($d['stime']):date('Y-m-d');
    if($stime){
      $start = strtotime($stime.' 00:00:00');
      $where[] = 'utime>='.$start;
    }
    $etime = isset($d['etime'])?trim($d['etime']):date('Y-m-d');
    if($etime){
      $end = strtotime($etime.' 23:59:59');
      $where[] = 'utime<='.$end;
    }
    // 关键字
    $key = isset($d['key'])?Util::Trim($d['key']):'';
    if($key){
      $arr = [
        'sku_id like "'.$key.'"',
      ];
      $where[] = '('.implode(' OR ', $arr).')';
    }
    // ID
    $ids = isset($d['ids'])?$d['ids']:[];
    if($ids) $where[] = 'id in('.implode(',', $ids).')';
    // 商品分类
    $category = isset($d['category'])?$d['category']:[];
    if($category) $where[] = 'category in("'.implode('","', $category).'")';
    // 仓库
    $wms_co_id = isset($d['wms_co_id'])?$d['wms_co_id']:[];
    if($wms_co_id) $where[] = 'wms_co_id in('.implode(',', $wms_co_id).')';
    // 商品编码
    $sku_id = isset($d['sku_id'])?trim($d['sku_id']):'';
    if($sku_id){
      if(strstr($sku_id, '%')){
        $where[] = 'sku_id like "'.$sku_id.'"';
      }else{
        $arr = array_values(array_filter(explode(' ', $sku_id)));
        foreach($arr as $k=>$v) $arr[$k]=Util::Trim($v);
        $where[] = 'sku_id in("'.implode('","', $arr).'")';
      }
    }
    // 数量
    $num1 = isset($d['num1'])?trim($d['num1']):'';
    $num2 = isset($d['num2'])?trim($d['num2']):'';
    if($num1!='' && $num2!='') $where[] = 'num>='.$num1.' AND num<='.$num2;
    // 采购员
    $owner = isset($d['owner'])?trim($d['owner']):'';
    if($owner) $where[] = 'owner LIKE "%'.$owner.'%"';
    // 客服仓
    $is_service = isset($d['is_service'])?$d['is_service']:false;
    if($is_service) {
      $m = new ErpBasePartner();
      $m->Columns('wms_co_id', 'name');
      $m->Where('class="客服仓"');
      $all = $m->Find();
      $wms_co_id = [];
      foreach($all as $v) $wms_co_id[]=$v['wms_co_id'];
      $where[] = 'wms_co_id in('.implode(',', $wms_co_id).')';
    }
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
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data)) return self::GetJSON(['code'=> 4000]);
    // 数据
    $admin = TokenAdmin::Token($token);
    $ids = isset($data['ids'])&&$data['ids']?$data['ids']:[];
    $wms_co_id = isset($data['wms_co_id'])&&$data['wms_co_id']?$data['wms_co_id']:[];
    $sku_id = isset($data['sku_id'])?trim($data['sku_id']):'';
    $num = isset($data['num'])?trim($data['num']):'';
    $remark = isset($data['remark'])?trim($data['remark']):'';
    if(!$wms_co_id || !$sku_id || !is_numeric($num)) return self::GetJSON(['code'=>4000]);
    // SKU
    $arr = explode(' ', preg_replace('/\n/', ' ', $sku_id));
    $sku = [];
    foreach($arr as $v) $sku[strtoupper(Util::Trim($v))]=1;
    // 数据
    $stock = $bizs = [];
    foreach($sku as $k=>$v) {
      $stock[$wms_co_id[0]][] = ['wms_co_id'=>$wms_co_id[0], 'sku_id'=>$k, 'num'=>$num];
      $bizs[$wms_co_id[0]][] = ['pid'=>date('YmdHis'), 'sku_id'=>$k, 'num'=>$num, 'remark'=> $admin->name.'['.$admin->uid.']'.$remark];
    }
    // 添加
    if($data['type']=='add' && $sku_id) {
      // 是否存在
      $m = new ErpPurchaseStock();
      $m->Columns('sku_id');
      $m->Where('wms_co_id=? AND sku_id in("'.implode('","', array_keys($sku)).'")', $wms_co_id[0]);
      $all = $m->Find();
      if($all) {
        $arr = [];
        foreach($all as $v) $arr[$v['sku_id']]=1;
        return self::GetJSON(['code'=>4000, 'msg'=>'[ '.implode(',', array_keys($arr)).' ]已存在!']);
      }
      // 库存
      Stock::Goods(json_encode(['bizs'=>$stock]));
    } elseif(in_array($data['type'], ['edit', 'edits'])) {
      // 编辑
      if(!$ids) return self::GetJSON(['code'=>4000]);
      // 更新
      $m = new ErpPurchaseStock();
      $m->Set(['num'=>$num, 'utime'=>time()]);
      $m->Where('id in('.implode(',', $ids).')');
      $m->Update();
    }
    // 日志
    foreach($sku as $sku_id=>$v) {
      Logs::Goods([
        'ctime'=> time(),
        'operator_id'=> $admin->uid,
        'operator_name'=> $admin->name,
        'sku_id'=> $sku_id,
        'content'=> '盘点: '.$sku_id.' 库存: '.$num.' 备注: '.$remark
      ]);
    }
    // 返回
    return self::GetJSON(['code'=>0]);
  }

  /* 删除 */
  static function Del(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = TokenAdmin::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data)) return self::GetJSON(['code'=> 4000]);
    // 数据
    $data = $data?$data:[];
    $ids = implode(',', $data);
    // 模型
    $m = new ErpPurchaseStock();
    $m->Where('id in('.$ids.')');
    return $m->Delete()?self::GetJSON(['code'=>0]):self::GetJSON(['code'=>5000]);
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
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data)) return self::GetJSON(['code'=> 4000]);
    // 条件
    $where = self::getWhere($data, $token);
    // 查询
    $m = new ErpPurchaseStock();
    $m->Columns('id', 'sku_id', 'num', 'wms_co_id', 'category', 'owner', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where($where);
    $m->Order($order?:'utime DESC');
    $list = $m->Find();
    // 数据
    $sku = [];
    foreach($list as $k=>$v) $sku[$v['sku_id']] = 0;
    // 分区
    $pname = Goods::GoodsInfoAll(array_keys($sku), 'pname', [0, 3]);
    // 入库
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('sku_id IN("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $in = [];
    foreach($all as $v) $in[$v['sku_id']] = $v['state'];
    // 退货
    $m = new ErpPurchaseOutShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('sku_id IN("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $out = [];
    foreach($all as $v) $out[$v['sku_id']] = $v['status'];
    // 调拨
    $m = new ErpPurchaseAllocateShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('sku_id in("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $allocate = [];
    foreach($all as $v) $allocate[$v['sku_id']]=$v['status'];
    // 发货
    $m = new ErpOrderShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('type in("1", "3") AND sku_id in("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $order = [];
    foreach($all as $v) $order[$v['sku_id']]=$v['status'];
    // 售后
    $m = new ErpOrderShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'status');
    $m->Where('type in("2", "4") AND sku_id in("'.implode('","', array_keys($sku)).'")');
    $m->Order('ctime', 'status');
    $all = $m->Find();
    $refund = [];
    foreach($all as $v) $refund[$v['sku_id']]=$v['status'];
    // 整理
    self::$partner_name = ErpBasePartner::GetList();
    foreach($list as $k=>$v) {
      $list[$k]['wms_co_name'] = self::$partner_name[$v['wms_co_id']]['name'];
      $list[$k]['in'] = isset($in[$v['sku_id']])?($in[$v['sku_id']]=='0'?'进行中':'完成'):'-';
      $list[$k]['out'] = isset($out[$v['sku_id']])?($out[$v['sku_id']]=='0'?'进行中':'完成'):'-';
      $list[$k]['allocate'] = isset($allocate[$v['sku_id']])?($allocate[$v['sku_id']]=='0'?'调拨中':'完成'):'-';
      $list[$k]['order'] = isset($order[$v['sku_id']])?($order[$v['sku_id']]=='0'?'进行中':'完成'):'-';
      $list[$k]['refund'] = isset($refund[$v['sku_id']])?($refund[$v['sku_id']]=='0'?'进行中':'完成'):'-';
      // 停留时间
      $now = date('Y-m-d').' 23:59:59';
      $dt1 = new \DateTime($v['utime']);
      $dt2 = new \DateTime($now);
      $res = $dt1->diff($dt2);
      $list[$k]['stay'] = $res->days;
    }
    // 导出文件
    $admin = TokenAdmin::Token($token);
    self::$export_filename = 'GoodsStock_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', '商品分类', '商品编码', '库存', '入库', '调拨', '发货', '售后', '采退', '分仓ID', '分仓名称', '采购员', '创建时间', '更新时间', '停留(天)'
    ]);
    // 数据
    foreach($list as $v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        $v['category'],
        '&nbsp;'.$v['sku_id'],
        $v['num'],
        $v['in'],
        $v['allocate'],
        $v['order'],
        $v['refund'],
        $v['out'],
        $v['wms_co_id'],
        $v['wms_co_name'],
        $v['owner'],
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
        $v['stay'],
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
    // 分类
    $category = [];
    $all = ErpBaseCategory::GetList();
    foreach($all as $v) $category[] = ['label'=>$v['name'], 'value'=>$v['name'], 'info'=>$v['status']?true:false];
    // 分仓
    $admin = TokenAdmin::Token($token);
    self::$partner_name = ErpBasePartner::GetList();
    $partner_name = [];
    foreach(self::$partner_name as $k=>$v) {
      // 权限限制
      if($admin->partner) {
        $arr = explode(',', $admin->partner);
        if(in_array($k, $arr)) $partner_name[]=['label'=>$v['name'], 'value'=>$k, 'info'=>$v['status']?true:false];
        continue;
      }
      // 全部
      $partner_name[]=['label'=>$v['name'], 'value'=>$k, 'info'=>$v['status']?true:false];
    }
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>[
      'category'=> $category,
      'partner_name'=> $partner_name,
    ]]);
  }

}
