<?php
namespace App\Admin;

use Config\Env;
use Service\AdminToken;
use Service\Base;
use Service\Logs;
use Service\Data as sData;
use Library\Export;
use Task\Stock;
use Data\Status;
use Data\Brand;
use Data\Partner;
use Data\Goods;
use Util\Util;
use Model\ErpPurchaseIn as ErpPurchaseInM;
use Model\ErpPurchaseInShow;

class ErpPurchaseInPush extends Base {

  static private $partner_name = [];                // 主仓
  static private $brand_name = [];                  // 品牌
  static private $type_name = [];                   // 类型
  static private $status_name = [];                 // 状态
  static private $export_path = 'upload/tmp/';      // 导出-目录

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
    $where = self::getWhere($data, $token);
    // 统计
    $m = new ErpPurchaseInM();
    $m->Columns('count(*) AS total', 'sum(num) AS num', 'sum(sale_price) AS sale_price', 'sum(market_price) AS market_price');
    $m->Where($where);
    $one = $m->FindFirst();
    $total = [
      'total'=> $one?(int)$one['total']:0,
      'num'=> $one?(int)$one['num']:0,
      'sale_price'=> $one?(float)$one['sale_price']:0,
      'market_price'=> $one?(float)$one['market_price']:0,
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
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data) || empty($page) || empty($limit)) return self::GetJSON(['code'=> 4000]);
    $where = self::getWhere($data, $token);
    // 查询
    $m = new ErpPurchaseInM();
    $m->Columns('id', 'type', 'wms_co_id', 'brand', 'sale_price', 'market_price', 'num', 'total', 'status', 'creator_id', 'creator_name', 'operator_id', 'operator_name', 'remark', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where($where);
    $m->Page($page, $limit);
    $m->Order($order?:'status, utime DESC');
    $list = $m->Find();
    // 数据
    self::$partner_name = Partner::GetList(['type=0']);
    self::$type_name = Status::PurchaseIn('type_name');
    self::$status_name = Status::PurchaseIn('status_name');
    foreach($list as $k=>$v) {
      $list[$k]['wms_co_name'] = self::$partner_name[$v['wms_co_id']]['name'];
      $list[$k]['type_name'] = self::$type_name[$v['type']];
      $list[$k]['status_name'] = self::$status_name[$v['status']];
    }
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>$list]);
  }
  /* 搜索条件 */
  static private function getWhere(array $d, string $token): string {
    $where = ['status in(1, 2)'];
    $admin = AdminToken::Token($token);
    // 限制-分仓
    if($admin->partner){
      $where[] = 'wms_co_id in('.$admin->partner.')';
    }
    // 时间
    $stime = isset($d['stime'])?trim($d['stime']):date('Y-m-d', strtotime('-1 year'));
    if($stime){
      $start = strtotime($stime.' 00:00:00');
      $where[] = 'ctime>='.$start;
    }
    $etime = isset($d['etime'])?trim($d['etime']):date('Y-m-d');
    if($etime){
      $end = strtotime($etime.' 23:59:59');
      $where[] = 'ctime<='.$end;
    }
    // 关键字
    $key = isset($d['key'])?Util::Trim($d['key']):'';
    if($key){
      $arr = [
        'id="'.$key.'"',
        'brand="'.$key.'"',
        'creator_name="'.$key.'"',
        'operator_name="'.$key.'"',
        'remark like "%'.$key.'%"',
      ];
      // 商品编码
      $pid = self::getPid($key, $start, $end);
      if($pid) $arr[] = 'id in('.implode(',', $pid).')';
      $where[] = '('.implode(' OR ', $arr).')';
    }
    // 仓库
    $wms_co_id = isset($d['wms_co_id'])&&is_array($d['wms_co_id'])?$d['wms_co_id']:[];
    if($wms_co_id) $where[] = 'wms_co_id in("'.implode('","', $wms_co_id).'")';
    // 类型
    $type = isset($d['type'])&&is_array($d['type'])?$d['type']:[];
    if($type) $where[] = 'type in("'.implode('","', $type).'")';
    // 状态
    $status = isset($d['status'])&&is_array($d['status'])?$d['status']:[];
    if($status) $where[] = 'status in('.implode(',', $status).')';
    // 品牌
    $brand = isset($d['brand'])&&is_array($d['brand'])?$d['brand']:[];
    if($brand) $where[] = 'brand in("'.implode('","', $brand).'")';
    // ID
    $id = isset($d['id'])?trim($d['id']):'';
    if($id){
      $arr = explode(' ', $id);
      $where[] = 'id in('.implode(',', $arr).')';
    }
    // 编码
    $sku_id = isset($d['sku_id'])?trim($d['sku_id']):'';
    if($sku_id){
      $pid = self::getPid($sku_id, $start, $end);
      $where[] = empty($pid)?'id=0':'id in('.implode(',', $pid).')';
    }
    // 制单员
    $creator_name = isset($d['creator_name'])?trim($d['creator_name']):'';
    if($creator_name) $where[] = 'creator_name like "%'.$creator_name.'%"';
    // 操作员
    $operator_name = isset($d['operator_name'])?trim($d['operator_name']):'';
    if($operator_name) $where[] = 'operator_name like "%'.$operator_name.'%"';
    // 备注
    $remark = isset($d['remark'])?trim($d['remark']):'';
    if($remark!='') $where[] = 'a.remark like "%'.$remark.'%"';
    // 返回
    return implode(' AND ', $where);
  }
  /* 搜索条件-PID */
  static function getPid(string $sku_id, $start, $end) {
    // 条件
    $arr = explode(' ', $sku_id);
    foreach($arr as $k=>$v) $arr[$k]=Util::Trim($v);
    $w = '';
    if($start) $w .= 'utime >= '.$start.' AND ';
    if($end) $w .= 'utime <= '.$end.' AND ';
    $w .= count($arr)==1?'sku_id="'.$arr[0].'"':'sku_id in('.'"'.implode('","', $arr).'"'.')';
    // 分区
    $pname = '';
    if($start && $end){
      $pname = sData::PartitionName($start, $end);
    }
    // 查询
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Columns('pid');
    $m->Where($w);
    $m->Group('pid');
    $all = $m->Find();
    // ID限制
    $ids = [];
    foreach($all as $v) $ids[]=$v['pid'];
    return $ids;
  }

  /* 推送 */
  static function Push(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 数据
    $id = implode(',', $data);
    $admin = AdminToken::Token($token);
    self::$partner_name = Partner::GetList(['type=0']);
    // 单据
    $m = new ErpPurchaseInM();
    $m->Columns('id', 'ctime', 'utime', 'wms_co_id', 'remark');
    $m->Where('status=1 AND id in('.$id.')');
    $info = $m->Find();
    if(!$info) return self::GetJSON(['code'=>4000, 'msg'=>'当前状态不可用!']);
    // 分区
    $pids = $ctime = $utime = [];
    foreach($info as $v) {
      $pids[] = $v['id'];
      $ctime[] = $v['ctime'];
      $utime[] = $v['utime'];
    }
    sort($ctime);
    rsort($utime);
    $pname = sData::PartitionName($ctime[0], $utime[0]);
    // 货品
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Columns('id', 'pid', 'wms_co_id', 'sku_id', 'num');
    $m->Where('status=0 AND pid in('.implode(',', $pids).')');
    $all = $m->Find();
    // 数据
    $ids = $bizs = [];
    foreach($all as $v) {
      $ids[] = $v['id'];
      $bizs[$v['wms_co_id']][] = [
        'wms_co_id'=>$v['wms_co_id'],
        'sku_id'=> $v['sku_id'],
        'num'=>$v['num'],
        // 其它
        'pid'=>$v['pid'],
      ];
    }
    // 明细
    if($ids) {
      $m = new ErpPurchaseInShow();
      if($pname) $m->Partition($pname);
      $m->Set(['status'=>1]);
      $m->Where('id in('.implode(',', $ids).')');
      $m->Update();
    }
    // 入库单
    $m = new ErpPurchaseInM();
    $m->Set(['status'=>2, 'utime'=>time(), 'operator_id'=>$admin->uid, 'operator_name'=>$admin->name]);
    $m->Where('status=1 AND id in('.implode(',', $pids).')');
    if($m->Update()) {
      // 库存盘点
      foreach($bizs as $k=>$v) {
        $list = array_chunk($v, 200, false);
        foreach($list as $d) {
          // 库存、聚水谭
          Stock::Goods(json_encode(['bizs'=>[$k=>$d], 'goods'=>true]));
          // 日志
          foreach($d as $sku) {
            Logs::Goods([
              'ctime'=>time(),
              'operator_id'=> $admin->uid,
              'operator_name'=> $admin->name,
              'sku_id'=> $sku['sku_id'],
              'content'=> '入库成功: '.$sku['sku_id'].' 单号: '.$sku['pid'].' 数量: '.$sku['num'].' 仓库: '.self::$partner_name[$sku['wms_co_id']]['name'],
            ]);
          }
        }
      }
      return self::GetJSON(['code'=>0]);
    } else {
      return self::GetJSON(['code'=>5000]);
    }
  }

  /* 撤回 */
  static function Revoke(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 数据
    $id = implode(',', $data);
    // 更新
    $m = new ErpPurchaseInM();
    $m->Set(['status'=>0, 'utime'=>time()]);
    $m->Where('status=1 AND id in('.$id.')');
    if($m->Update()) {
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
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data)) return self::GetJSON(['code'=> 4000]);
    // 查询
    $m = new ErpPurchaseInM();
    $m->Columns('id', 'type', 'status', 'creator_name', 'operator_name', 'remark');
    $m->Where('id in('.implode(',', $data).')');
    $all = $m->Find();
    $pid = $info = [];
    foreach($all as $v){
      $pid[$v['id']] = 1;
      $info[$v['id']] = $v;
    }
    if(!$pid) return self::GetJSON(['code'=>4000, 'msg'=>'暂无数据!']);
    // 明细
    $m = new ErpPurchaseInShow();
    $m->Columns('pid', 'wms_co_id', 'type', 'sku_id', 'num', 'status', 'operator_name', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where('pid in('.implode(',', array_keys($pid)).')');
    $m->Order('pid DESC', 'id DESC');
    $list = $m->Find();
    // 商品资料
    $sku = [];
    foreach($list as $v) $sku[] = $v['sku_id'];
    $goods = $sku?Goods::GoodsInfoAll($sku):[];
    // 表头
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      '单号', '类型', '入库仓库', '图片', '商品编码', '暗码', '商品名称', '颜色及规格', '供应链价(元)', '标签价(元)', '吊牌价(W)', '数量', '折扣', '单位', '重量', '标签', '商品分类', '品牌', '款式编码', '采购员', '状态', '制单员', '操作员', '创建时间', '修改时间', '备注'
    ]);
    // 内容
    self::$partner_name = Partner::GetList();
    self::$type_name = Status::PurchaseIn('type_name');
    self::$status_name = Status::PurchaseIn('status_name');
    foreach($list as $v){
      $tmp = isset($goods[$v['sku_id']])?$goods[$v['sku_id']]:[];
      $html .= Export::ExcelData([
        $v['pid'],
        self::$type_name[$info[$v['pid']]['type']],
        self::$partner_name[$v['wms_co_id']]['name'],
        $tmp['img']?'<a href="'.sData::ImgGoods($v['sku_id'], false).'" target="_blank">查看</a>':'-',
        '&nbsp;'.$v['sku_id'],
        '&nbsp;'.($tmp['short_name']?$tmp['short_name']:'-'),
        $tmp['name']?$tmp['name']:'-',
        '&nbsp;'.($tmp['properties_value']?$tmp['properties_value']:'-'),
        $tmp['supply_price']>0?round($tmp['supply_price']*$v['num']*$tmp['ratio'], 2):'-',
        $tmp['sale_price']>0?round($tmp['sale_price']*$v['num']*$tmp['ratio'], 2):'-',
        $tmp['market_price']>0?round($tmp['market_price']*$v['num']*$tmp['ratio'], 2):'-',
        $v['num'],
        $tmp?$tmp['ratio']:1.00,
        $tmp['unit']?$tmp['unit']:'-',
        $tmp['weight']>0?$tmp['weight']:'-',
        $tmp['labels']?$tmp['labels']:'-',
        $tmp['category']?$tmp['category']:'-',
        $tmp['brand']?$tmp['brand']:'-',
        $tmp['i_id']?$tmp['i_id']:'-',
        $tmp['owner']?$tmp['owner']:'-',
        self::$status_name[$info[$v['pid']]['status']],
        $info[$v['pid']]['creator_name'],
        $v['operator_name'],
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
        $info[$v['pid']]['remark'],
      ]);
    }
    $html .= Export::ExcelBottom();
    // 文件名
    $admin = AdminToken::Token($token);
    $file_name = 'PurchaseInPush_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    Export::ExcelFileEnd(self::$export_path, $file_name, $html);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['path'=>Env::BaseUrl(self::$export_path), 'filename'=>$file_name]]);
  }

  /* 选项 */
  static function GetSelect(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    // 权限
    $admin = AdminToken::Token($token);
    $partner_perm = $admin->partner?explode(',', $admin->partner):[];
    $brand_perm = $admin->brand?explode(',', $admin->brand):[];
    // 分仓
    $partner_name = [];
    self::$partner_name = Partner::GetList(['type=0'], ['name', 'status']);
    foreach(self::$partner_name as $k=>$v) {
      $tmp = ['label'=>$v['name'], 'value'=>$k, 'info'=>$v['status']?'正常':'禁用'];
      if($partner_perm) {
        if(in_array($k, $partner_perm)) $partner_name[] = $tmp;
      } else $partner_name[] = $tmp;
    }
    //  品牌
    $brand_name = [];
    self::$brand_name = Brand::GetList();
    foreach(self::$brand_name as $k=>$v) {
      $tmp = ['label'=>$v['name'], 'value'=>$k];
      if($brand_perm) {
        if(in_array($k, $brand_perm)) $brand_name[] = $tmp;
      } else $brand_name[] = $tmp;
    }
    // 类型
    $type_name = [];
    self::$type_name = Status::PurchaseIn('type_name');
    foreach(self::$type_name as $k=>$v) $type_name[]=['label'=>$v, 'value'=>$k];
    // 状态
    $status_name = [];
    self::$status_name = Status::PurchaseIn('status_name');
    foreach(self::$status_name as $k=>$v) $status_name[]=['label'=>$v, 'value'=>$k];
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>[
      'partner_name'=> $partner_name,
      'brand_name'=> $brand_name,
      'type_name'=> $type_name,
      'status_name'=> $status_name,
    ]]);
  }

  /* 商品-列表 */
  static function GoodsList(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $id = self::JsonName($json, 'id');
    $order = self::JsonName($json, 'order');
    $sku_id = self::JsonName($json, 'sku_id');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($id)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 查询
    $m = new ErpPurchaseInM();
    $m->Columns('id', 'ctime', 'utime', 'wms_co_id');
    $m->Where('id=?', $id);
    $one = $m->FindFirst();
    if(!$one) return self::GetJSON(['code'=>4000]);
    // 分区
    $pname = sData::PartitionName($one['ctime'], $one['utime']);
    $where = ['pid='.$id, 'wms_co_id='.$one['wms_co_id']];
    // SKU
    if($sku_id) {
      $arr = array_filter(explode(' ', strtoupper($sku_id)));
      $where[] = 'sku_id in("'.implode('","', $arr).'")';
    }
    // 查询
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Columns('id', 'sku_id', 'wms_co_id', 'num', 'status', 'operator_name', 'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime');
    $m->Where(implode(' AND ', $where));
    $m->Order('id DESC');
    $list = $m->Find();
    // 商品资料
    $sku = [];
    foreach($list as $v) $sku[] = $v['sku_id'];
    $goods = $sku?Goods::GoodsInfoAll($sku):[];
    // 数据
    foreach($list as $k=>$v) {
      if(!isset($goods[$v['sku_id']])) continue;
      $info = $goods[$v['sku_id']];
      $v['category'] = $info['category'];
      $v['name'] = $info['name'];
      $v['short_name'] = $info['short_name'];
      $v['properties_value'] = $info['properties_value'];
      $v['supply_price'] = $info['supply_price'];
      $v['sale_price'] = $info['sale_price'];
      $v['market_price'] = $info['market_price'];
      $v['unit'] = $info['unit'];
      $v['weight'] = $info['weight'];
      $v['ratio'] = $info['ratio'];
      $v['labels'] = $info['labels'];
      $v['brand'] = $info['brand'];
      $v['owner'] = $info['owner'];
      $v['i_id'] = $info['i_id'];
      $v['img'] = $info['img']?sData::ImgGoods($v['sku_id'], false):'';
      $list[$k] = $v;
    }
    // 排序
    if($order){
      $arr = explode(' ', $order);
      $list = Util::AarraySort($list, $arr[0], $arr[1]=='ASC'?SORT_ASC:SORT_DESC);
    }
    // 结果
    return self::GetJSON(['code'=>0, 'data'=>$list]);
  }

}