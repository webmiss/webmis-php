<?php

namespace App\Admin;

use Config\Env;
use Service\AdminToken;
use Service\Base;
use Service\Data as sData;
use Service\Logs;
use Data\Status;
use Data\Goods;
use Data\Brand;
use Data\Partner;
use Data\Category;
use Library\Export;
use Task\Stock;
use Model\ErpGoodsInfo as ErpGoodsInfoM;
use Model\ErpPurchaseInShow;
use Model\ErpOrderShow;
use Model\ErpPurchaseStock;
use Model\ErpBaseSupplier;
use Util\Util;

/* 商品资料 */
class ErpGoodsInfo extends Base {

  static private $partner_name = [];      // 分仓
  // 价格名称
  static private $price_name = [
    'cost_price'=> '成本价',
    'purchase_price'=> '采购价',
    'supply_price'=> '供应链价',
    'supplier_price'=> '人民币采购价',
    'sale_price'=> '标签价',
    'market_price'=> '吊牌价',
    'order_price'=> '开单价',
    'play_price'=> '实付价',
  ];
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
    if($msg != '') return self::GetJSON(['code'=> 4001, 'msg'=> $msg]);
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=> 4000, 'msg'=> '参数错误!']);
    }
    // 条件
    list($where, $pname) = self::getWhere($data, $token);
    $admin = AdminToken::Token($token);
    $isPrice = Goods::isPrice($admin);
    // 统计
    $m = new ErpGoodsInfoM();
    if($pname) $m->Partition($pname);
    $m->Columns('count(*) AS total', 'sum(cost_price) as cost_price', 'sum(sale_price) as sale_price', 'sum(purchase_price*num) as purchase_price', 'sum(market_price*num) as market_price', 'sum(num) as num');
    $m->Where($where);
    $one = $m->FindFirst();
    $total = [
      'total'=> $one?(int)$one['total']:0,
      'cost_price'=> $one&&$isPrice?(float)$one['cost_price']:0,
      'sale_price'=> $one?(float)$one['sale_price']:0,
      'purchase_price'=> $one&&$isPrice?(float)$one['purchase_price']:0,
      'market_price'=> $one?(float)$one['market_price']:0,
      'num'=> $one?(int)$one['num']:0,
    ];
    // 返回
    return self::GetJSON(['code'=> 0, 'msg'=> '成功', 'time'=> date('Y/m/d H:i:s'), 'data'=> $total]);
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
    if($msg != '') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data) || empty($page) || empty($limit)) return self::GetJSON(['code'=> 4000]);
    // 条件
    list($where, $pname) = self::getWhere($data, $token);
    // 查询
    $m = new ErpGoodsInfoM();
    if($pname) $m->Partition($pname);
    $m->Columns(
      'id', 'img', 'sku_id', 'i_id', 'owner', 'name', 'short_name', 'properties_value', 'unit', 'weight', 'category', 'labels', 'brand', 'supplier_id', 'supplier_name', 'operator_id', 'operator_name', 'remark', 'cost_price', 'purchase_price', 'supply_price', 'supplier_price', 'sale_price', 'market_price', 'other_price', 'other_price1', 'num',
      'ratio', 'ratio_cost', 'ratio_purchase', 'ratio_supply', 'ratio_supplier', 'ratio_sale', 'ratio_market',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime'
    );
    $m->Where($where);
    $m->Page($page, $limit);
    $m->Order($order ?: 'id DESC');
    $list = $m->Find();
    // 剩余库存
    $sku = [];
    foreach($list as $v) $sku[$v['sku_id']] = ['num'=>0, 'wms_co_id'=>0, 'wms_co_name'=>''];
    if($sku) {
      $m = new ErpPurchaseStock();
      $m->Columns('sku_id', 'num', 'wms_co_id');
      $m->Where('num>0 AND sku_id in("'.implode('","', array_keys($sku)).'")');
      $all = $m->Find();
      self::$partner_name = Partner::GetList();
      foreach($all as $v) {
        $sku[$v['sku_id']]['num'] = $v['num'];
        $sku[$v['sku_id']]['wms_co_id'] = $v['wms_co_id'];
        $sku[$v['sku_id']]['wms_co_name'] = self::$partner_name[$v['wms_co_id']]['name'];
      }
    }
    // 数据
    $admin = AdminToken::Token($token);
    foreach($list as $k=>$v) {
      // 封面图
      $list[$k]['img'] = $v['img']?sData::ImgGoods($v['sku_id'], false):'';
      // 成本价
      $isPrice = Goods::isPrice($admin, ['owner'=>$v['owner']]);
      $list[$k]['cost_price'] = $isPrice?$v['cost_price']:'-';
      $list[$k]['purchase_price'] = $isPrice?$v['purchase_price']:'-';
      $list[$k]['supplier_name'] = $isPrice?$v['supplier_name']:'-';
      // 库存信息
      $list[$k]['num'] = $sku[$v['sku_id']]['num'];
      $list[$k]['wms_co_id'] = $sku[$v['sku_id']]['wms_co_id'];
      $list[$k]['wms_co_name'] = $sku[$v['sku_id']]['wms_co_name'];
    }
    // 返回
    return self::GetJSON(['code'=> 0, 'time'=> date('Y/m/d H:i:s'), 'data'=> $list]);
  }
  /* 搜索条件 */
  private static function getWhere(array $d, string $token): array {
    $where = [];
    $admin = AdminToken::Token($token);
    // 限制-品牌
    if($admin->brand) {
      $brand = explode(',', $admin->brand);
      $where[] = 'brand in("' . implode('","', $brand) . '")';
    }
    // 时间
    $stime = isset($d['stime']) && !empty($d['stime'])?trim($d['stime']):date('Y-m-d', strtotime('-1 year'));
    if($stime) {
      $start = strtotime($stime . ' 00:00:00');
      $where[] = 'ctime>=' . $start;
    }
    $etime = isset($d['etime']) && !empty($d['etime'])?trim($d['etime']):date('Y-m-d');
    if($etime) {
      $end = strtotime($etime . ' 23:59:59');
      $where[] = 'ctime<=' . $end;
    }
    // 分区
    $pname = '';
    if($stime && $etime) {
      $stime = strtotime($stime);
      $etime = strtotime($etime);
      $pname = sData::PartitionName($stime, $etime);
    }
    // 关键字
    $key = isset($d['key'])?trim($d['key']):'';
    if($key) {
      $arr = [
        'sku_id="' . $key . '"',
      ];
      $where[] = '(' . implode(' OR ', $arr) . ')';
    }
    // 编码
    $sku_id = isset($d['sku_id'])?trim($d['sku_id']):'';
    if($sku_id) {
      if(strstr($sku_id, '%')) {
        $where[] = 'sku_id like "' . $sku_id . '"';
      } else {
        $arr = array_values(array_filter(explode(' ', $sku_id)));
        foreach($arr as $k=> $v) $arr[$k] = trim($v);
        $where[] = 'sku_id in("' . implode('","', $arr) . '")';
      }
    }
    // 暗码
    $short_name = isset($d['short_name'])?trim($d['short_name']):'';
    if($short_name) {
      if(strstr($short_name, '%')) {
        $where[] = 'short_name like "' . $short_name . '"';
      } else {
        $arr = array_values(array_filter(explode(' ', $short_name)));
        foreach($arr as $k=> $v) $arr[$k] = trim($v);
        $where[] = 'short_name in("' . implode('","', $arr) . '")';
      }
    }
    // 款式编码
    $i_id = isset($d['i_id'])?trim($d['i_id']):'';
    if($i_id) {
      if(strstr($i_id, '%')) {
        $where[] = 'i_id like "' . $i_id . '"';
      } else {
        $arr = explode(' ', $i_id);
        foreach($arr as $k=> $v) $arr[$k] = trim($v);
        $where[] = 'i_id in("' . implode('","', $arr) . '")';
      }
    }
    // 库存
    $num1 = isset($d['num1'])?trim($d['num1']):'';
    $num2 = isset($d['num2'])?trim($d['num2']):'';
    if($num1 != '' && $num2 != '') $where[] = 'num>=' . $num1 . ' AND num<=' . $num2;
    // 成本价
    $cost_price1 = isset($d['cost_price1'])?trim($d['cost_price1']):'';
    $cost_price2 = isset($d['cost_price2'])?trim($d['cost_price2']):'';
    if($cost_price1 != '' && $cost_price2 != '') $where[] = 'cost_price>=' . $cost_price1 . ' AND cost_price<=' . $cost_price2;
    // 供货价
    $supply_price1 = isset($d['supply_price1'])?trim($d['supply_price1']):'';
    $supply_price2 = isset($d['supply_price2'])?trim($d['supply_price2']):'';
    if($supply_price1 != '' && $supply_price2 != '') $where[] = 'supply_price>=' . $supply_price1 . ' AND supply_price<=' . $supply_price2;
    // 标签价
    $sale_price1 = isset($d['sale_price1'])?trim($d['sale_price1']):'';
    $sale_price2 = isset($d['sale_price2'])?trim($d['sale_price2']):'';
    if($sale_price1 != '' && $sale_price2 != '') $where[] = 'sale_price>=' . $sale_price1 . ' AND sale_price<=' . $sale_price2;
    // 采购价
    $purchase_price1 = isset($d['purchase_price1'])?trim($d['purchase_price1']):'';
    $purchase_price2 = isset($d['purchase_price2'])?trim($d['purchase_price2']):'';
    if($purchase_price1 != '' && $purchase_price2 != '') $where[] = 'purchase_price>=' . $purchase_price1 . ' AND purchase_price<=' . $purchase_price2;
    // 结算价
    $supplier_price1 = isset($d['supplier_price1'])?trim($d['supplier_price1']):'';
    $supplier_price2 = isset($d['supplier_price2'])?trim($d['supplier_price2']):'';
    if($supplier_price1 != '' && $supplier_price2 != '') $where[] = 'supplier_price>=' . $supplier_price1 . ' AND supplier_price<=' . $supplier_price2;
    // 吊牌价
    $market_price1 = isset($d['market_price1'])?trim($d['market_price1']):'';
    $market_price2 = isset($d['market_price2'])?trim($d['market_price2']):'';
    if($market_price1 != '' && $market_price2 != '') $where[] = 'market_price>=' . $market_price1 . ' AND market_price<=' . $market_price2;
    // 重量
    $weight1 = isset($d['weight1'])?trim($d['weight1']):'';
    $weight2 = isset($d['weight2'])?trim($d['weight2']):'';
    if($weight1 != '' && $weight2 != '') $where[] = 'weight>=' . $weight1 . ' AND weight<=' . $weight2;
    // 标签
    $labels = isset($d['labels']) && is_array($d['labels'])?$d['labels']:[];
    if($labels) $where[] = 'labels in("' . implode('","', $labels) . '")';
    // 分类
    $category = isset($d['category']) && is_array($d['category'])?$d['category']:[];
    if($category) $where[] = 'category in("' . implode('","', $category) . '")';
    // 名称
    $name = isset($d['name'])?trim($d['name']):'';
    if($name != '') $where[] = 'name="' . $name . '"';
    // 颜色及规格
    $properties_value = isset($d['properties_value'])?trim($d['properties_value']):'';
    if($properties_value != '') $where[] = 'properties_value LIKE "%' . $properties_value . '%"';
    // 品牌
    $brand = isset($d['brand'])?trim($d['brand']):'';
    if($brand != '') $where[] = 'brand="' . $brand . '"';
    // 采购员
    $owner = isset($d['owner'])?trim($d['owner']):'';
    if($owner != '') $where[] = 'owner="' . $owner . '"';
    // 供应商
    $supplier_name = isset($d['supplier_name'])?trim($d['supplier_name']):'';
    if($supplier_name) {
      if(strstr($supplier_name, '%')) $where[] = 'supplier_name LIKE "' . $supplier_name . '"';
      else $where[] = 'supplier_name="' . $supplier_name . '"';
    }
    // 返回
    return [implode(' AND ', $where), $pname];
  }

  /* 保存 */
  static function Save(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data)) return self::GetJSON(['code'=> 4000]);
    // 数据
    $ids = isset($data['ids']) && is_array($data['ids'])?$data['ids']:[];
    $sku = explode(',', $data['sku_id']);
    if(!$sku) return self::GetJSON(['code'=> 4000, 'msg'=> '请输入商品编码']);
    $param = [];
    if(isset($data['name']) && $data['name'] != '') $param['name'] = Util::Trim($data['name']);
    if(isset($data['properties_value']) && $data['properties_value'] != '') $param['properties_value'] = Util::Trim($data['properties_value']);
    if(isset($data['short_name']) && $data['short_name'] != '') $param['short_name'] = Util::Trim($data['short_name']);
    if(isset($data['cost_price']) && $data['cost_price'] != '') $param['cost_price'] = Util::Trim($data['cost_price']);
    if(isset($data['supply_price']) && $data['supply_price'] != '') $param['supply_price'] = Util::Trim($data['supply_price']);
    if(isset($data['sale_price']) && $data['sale_price'] != '') $param['sale_price'] = Util::Trim($data['sale_price']);
    if(isset($data['purchase_price']) && $data['purchase_price'] != '') $param['purchase_price'] = Util::Trim($data['purchase_price']);
    if(isset($data['supplier_price']) && $data['supplier_price'] != '') $param['supplier_price'] = Util::Trim($data['supplier_price']);
    if(isset($data['market_price']) && $data['market_price'] != '') $param['market_price'] = Util::Trim($data['market_price']);
    if(isset($data['other_price']) && $data['other_price'] != '') $param['other_price'] = Util::Trim($data['other_price']);
    if(isset($data['other_price1']) && $data['other_price1'] != '') $param['other_price1'] = Util::Trim($data['other_price1']);
    if(isset($data['unit']) && $data['unit'] != '') $param['unit'] = Util::Trim($data['unit']);
    if(isset($data['weight']) && $data['weight'] != '') $param['weight'] = Util::Trim($data['weight']);
    if(isset($data['num']) && $data['num'] != '') $param['num'] = Util::Trim($data['num']);
    if(isset($data['ratio']) && $data['ratio'] != '') $param['ratio'] = Util::Trim($data['ratio']);
    if(isset($data['ratio_cost']) && $data['ratio_cost'] != '') $param['ratio_cost'] = Util::Trim($data['ratio_cost']);
    if(isset($data['ratio_purchase']) && $data['ratio_purchase'] != '') $param['ratio_purchase'] = Util::Trim($data['ratio_purchase']);
    if(isset($data['ratio_supply']) && $data['ratio_supply'] != '') $param['ratio_supply'] = Util::Trim($data['ratio_supply']);
    if(isset($data['ratio_supplier']) && $data['ratio_supplier'] != '') $param['ratio_supplier'] = Util::Trim($data['ratio_supplier']);
    if(isset($data['ratio_sale']) && $data['ratio_sale'] != '') $param['ratio_sale'] = Util::Trim($data['ratio_sale']);
    if(isset($data['ratio_market']) && $data['ratio_market'] != '') $param['ratio_market'] = Util::Trim($data['ratio_market']);
    if(isset($data['owner']) && $data['owner'] != '') $param['owner'] = Util::Trim($data['owner']);
    if(isset($data['i_id']) && $data['i_id'] != '') $param['i_id'] = Util::Trim($data['i_id']);
    if(isset($data['supplier_name']) && $data['supplier_name'] != '') $param['supplier_name'] = Util::Trim($data['supplier_name']);
    if(isset($data['labels']) && $data['labels']) $param['labels'] = $data['labels'][0];
    if(isset($data['category']) && $data['category']) $param['category'] = $data['category'][0];
    if(isset($data['brand']) && $data['brand']) $param['brand'] = $data['brand'][0];
    if(!$param) return self::GetJSON(['code'=> 4000]);
    $remark = isset($data['remark'])&&$data['remark']?' '.Util::Trim($data['remark']):'';
    // 商品资料
    $info = Goods::GoodsInfoAll($sku);
    $sku = array_keys($info);
    // 分区
    $pname = Goods::GoodsInfoAll($sku, 'pname');
    // 更新
    $admin = AdminToken::Token($token);
    $param['utime'] = time();
    $param['operator_id'] = $admin->uid;
    $param['operator_name'] = $admin->name;
    $m = new ErpGoodsInfoM();
    if($pname) $m->Partition($pname);
    $m->Set($param);
    $m->Where('id in(' . implode(',', $ids) . ')');
    if($m->Update()) {
      // 清理
      if(isset($param['num'])) unset($param['num']);
      if(isset($param['utime'])) unset($param['utime']);
      if(isset($param['operator_id'])) unset($param['operator_id']);
      if(isset($param['operator_name'])) unset($param['operator_name']);
      // 日志
      $biz = [];
      foreach($sku as $sku_id) {
        $msg = Goods::GoodsInfoDiff($param, $info[$sku_id]);
        Logs::Goods([
          'ctime'=> time(),
          'operator_id'=> $admin->uid,
          'operator_name'=> $admin->name,
          'sku_id'=> $sku_id,
          'content'=> '更新商品: ' . $sku_id . $msg.$remark,
        ]);
      }
    }
    // 更新明细
    if(isset($param['other_price'])) unset($param['other_price']);
    if(isset($param['other_price1'])) unset($param['other_price1']);
    if(isset($param['ratio_cost'])) unset($param['ratio_cost']);
    if(isset($param['ratio_purchase'])) unset($param['ratio_purchase']);
    if(isset($param['ratio_supply'])) unset($param['ratio_supply']);
    if(isset($param['ratio_supplier'])) unset($param['ratio_supplier']);
    if(isset($param['ratio_sale'])) unset($param['ratio_sale']);
    if(isset($param['ratio_market'])) unset($param['ratio_market']);
    $res = $param?Goods::GoodsUpdateShow($sku, $param):true;
    // 返回
    if($res) return self::GetJSON(['code'=> 0]);
    else return self::GetJSON(['code'=> 5000]);
  }

  /* 删除 */
  static function Del(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=> 4001]);
    if(empty($data)) return self::GetJSON(['code'=> 4000]);
    // 数据
    $ids = implode(',', $data);
    // 日志
    $m = new ErpGoodsInfoM();
    $m->Columns('sku_id');
    $m->Where('id IN(' . $ids . ')');
    $all = $m->Find();
    $admin = AdminToken::Token($token);
    foreach($all as $v) {
      Logs::Goods([
        'ctime'=> time(),
        'operator_id'=> $admin->uid,
        'operator_name'=> $admin->name,
        'sku_id'=> $v['sku_id'],
        'content'=> '删除商品: ' . $v['sku_id'],
      ]);
    }
    // 执行
    $m = new ErpGoodsInfoM();
    $m->Where('id IN(' . $ids . ')');
    return $m->Delete()?self::GetJSON(['code'=> 0]):self::GetJSON(['code'=> 5000]);
  }

  /* 导出 */
  static function Export(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    $order = self::JsonName($json, 'order');
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data)) return self::GetJSON(['code'=> 4000]);
    // 条件
    list($where, $pname) = self::getWhere($data, $token);
    // 查询
    $m = new ErpGoodsInfoM();
    if($pname) $m->Partition($pname);
    $m->Columns(
      'id', 'img', 'sku_id', 'i_id', 'owner', 'name', 'short_name', 'properties_value', 'cost_price', 'purchase_price', 'supply_price', 'supplier_price', 'sale_price', 'market_price', 'other_price', 'other_price1', 'unit', 'weight', 'num', 'labels', 'category', 'brand', 'supplier_name',
      'ratio', 'ratio_cost', 'ratio_purchase', 'ratio_supply', 'ratio_supplier', 'ratio_sale', 'ratio_market',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime'
    );
    $m->Where($where);
    $m->Order($order ?: 'utime DESC, id DESC');
    $list = $m->Find();
    // 剩余库存
    $sku = [];
    foreach($list as $v) $sku[$v['sku_id']] = ['num'=>0, 'wms_co_id'=>0, 'wms_co_name'=>''];
    if($sku) {
      $m = new ErpPurchaseStock();
      $m->Columns('sku_id', 'num', 'wms_co_id');
      $m->Where('num>0 AND sku_id in("'.implode('","', array_keys($sku)).'")');
      $all = $m->Find();
      self::$partner_name = Partner::GetList();
      foreach($all as $v) {
        $sku[$v['sku_id']]['num'] = $v['num'];
        $sku[$v['sku_id']]['wms_co_id'] = $v['wms_co_id'];
        $sku[$v['sku_id']]['wms_co_name'] = self::$partner_name[$v['wms_co_id']]['name'];
      }
    }
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'Goods_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', '图片', '商品编码', '暗码', '款式编码', '商品名称', '颜色及规格', '成本价(元)', '成本折扣', '供应链价(元)', '供应链折扣', '标签价(元)', '标签折扣', '采购价(W)', '采购折扣', '人民币采购价(元)', '人民币折扣', '吊牌价(W)', '吊牌折扣', '参照价(元)', '其它价格1', '单位', '重量', '折扣', '库存', '仓库名称', '标签', '商品分类', '品牌', '供应商', '采购员', '创建时间', '更新时间'
    ]);
    foreach($list as $v) {
      // 成本价
      $isPrice = Goods::isPrice($admin, ['owner'=>$v['owner']]);
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        $v['img']?'<a href="'.sData::ImgGoods($v['sku_id'], false).'" target="_blank">查看</a>': '-',
        '&nbsp;'.$v['sku_id'],
        $v['short_name'],
        $v['i_id'],
        $v['name'],
        $v['properties_value'],
        $isPrice?$v['cost_price']:'***',
        $v['ratio_cost'],
        $v['supply_price'],
        $v['ratio_supply'],
        $v['sale_price'],
        $v['ratio_sale'],
        $isPrice?$v['purchase_price']:'***',
        $v['ratio_purchase'],
        $v['supplier_price'],
        $v['ratio_supplier'],
        $v['market_price'],
        $v['ratio_market'],
        $v['other_price'],
        $v['other_price1'],
        $v['unit'],
        $v['weight'],
        $v['ratio'],
        $sku[$v['sku_id']]['num'],
        $sku[$v['sku_id']]['wms_co_name'],
        $v['labels'],
        $v['category'],
        $v['brand'],
        $isPrice?$v['supplier_name']:'***',
        $v['owner'],
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
      ]);
    }
    $html .= Export::ExcelBottom();
    Export::ExcelFileEnd(self::$export_path, self::$export_filename, $html);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['path'=>Env::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]]);
  }

  /* 导入 */
  static function Import(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    $price = self::JsonName($json, 'price');
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg != '') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data)) return self::GetJSON(['code'=> 4000]);
    // 是否当月
    foreach($data as $k=> $v) {
      $sku_id = strtoupper(Util::Trim((string)$v['sku_id']));
      if(!$sku_id) return self::GetJSON(['code'=> 4000, 'msg'=> '无商品编码!']);
      $day = substr(date('Ymd'), 2, 4);
      $res = sData::PartitionSkuData($sku_id);
      if(!$res) return self::GetJSON(['code'=> 4000, 'msg'=> '商品编码必须包涵日期，如:"' . $day . '"']);
      // 调整价格
      if($price) $data[$k]['sale_price'] = $v['price'];
    }
    // 商品资料认证
    $data = Goods::GoodsInfoVerify($data);
    if(is_string($data)) return self::GetJSON(['code'=> 4000, 'msg'=> $data]);
    // 商品资料
    $info = Goods::GoodsInfoAll(array_keys($data));
    // 数据
    $values = $supplier = [];
    $admin = AdminToken::Token($token);
    foreach($data as $k=> $v) {
      if(isset($v['num'])) unset($v['num']);
      if(isset($info[$k])) {
        // 分区
        $pname = Goods::GoodsInfo($k, 'pname');
        // 更新
        $v['operator_id'] = $admin->uid;
        $v['operator_name'] = $admin->name;
        $v['utime'] = time();
        $m = new ErpGoodsInfoM();
        if($pname) $m->Partition($pname);
        $m->Set($v);
        $m->Where('sku_id=?', $k);
        $m->Update();
        // 日志
        $msg = Goods::GoodsInfoDiff($v, $info[$k]);
        Logs::Goods([
          'ctime'=> time(),
          'operator_id'=> $admin->uid,
          'operator_name'=> $admin->name,
          'sku_id'=> $k,
          'content'=> '更新资料: ' . $k . $msg,
        ]);
      } else {
        // 新增
        $v['ctime'] = time();
        $v['utime'] = time();
        $v['pdate'] = date('Y-m-d', strtotime('20'.sData::PartitionSkuData($k)));
        $values[] = $v;
        // 日志
        Logs::Goods([
          'ctime'=> time(),
          'operator_id'=> $admin->uid,
          'operator_name'=> $admin->name,
          'sku_id'=> $k,
          'content'=> '商品资料: '.$k,
        ]);
        // 供应商
        if(isset($v['supplier_name']) && $v['supplier_name']) $supplier[trim($v['supplier_name'])]=1;
      }
    }
    // 添加
    if($values) {
      // 商品资料
      $m = new ErpGoodsInfoM();
      $m->ValuesAll($values);
      $m->Insert();
      // 供应商
      $m = new ErpBaseSupplier();
      $m->Columns('name');
      $m->Where('name in("'.implode('","', array_keys($supplier)).'")');
      $all = $m->Find();
      $supplier_name = [];
      foreach($all as $v) $supplier_name[$v['name']]=1;
      $supplier_name = array_diff(array_keys($supplier), array_keys($supplier_name));
      if($supplier_name) {
        $values = [];
        foreach($supplier_name as $name) {
          $values[] = [
            'supplier_id'=> 0,
            'name'=> $name,
            'ctime'=> time(),
            'utime'=> time(),
            'operator_id'=> $admin->uid,
            'operator_name'=> $admin->name,
          ];
        }
        $m = new ErpBaseSupplier();
        $m->ValuesAll($values);
        $m->Insert();
      }
    }
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s')]);
  }

  /* 换算汇率 */
  static function Exchange(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    $form = self::JsonName($json, 'form');
    // 验证权限
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data) || empty($form) || !is_array($form)) return self::GetJSON(['code'=> 4000]);
    // 数据
    $from_price = isset($form['from_price']) && is_array($form['from_price'])?$form['from_price'][0]:'';
    $to_price = isset($form['to_price']) && is_array($form['to_price'])?$form['to_price'][0]:'';
    $rate = isset($form['rate']) && is_numeric($form['rate'])?$form['rate']:'';
    if(!$from_price || !$to_price || $rate == '') return self::GetJSON(['code'=> 4000]);
    // 查询
    list($where, $pname) = self::getWhere($data, $token);
    $m = new ErpGoodsInfoM();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', $from_price, $to_price);
    $m->Where($where);
    $all = $m->Find();
    // 更新
    $admin = AdminToken::Token($token);
    foreach($all as $v) {
      // 商品资料
      $data = [$to_price=> $v[$from_price] * $rate];
      $m->Set($data);
      $m->Where('sku_id=?', $v['sku_id']);
      $m->Update();
      // 明细
      Goods::GoodsUpdateShow([$v['sku_id']], $data);
      // 日志
      Logs::Goods([
        'ctime'=> time(),
        'operator_id'=> $admin->uid,
        'operator_name'=> $admin->name,
        'sku_id'=> $v['sku_id'],
        'content'=> '汇率换算: ' . $v['sku_id'] . ' 比率: ' . $rate . ' ' . self::$price_name[$from_price] . ' > ' . self::$price_name[$to_price],
      ]);
    }
    // 返回
    return self::GetJSON(['code'=> 0, 'time'=> date('Y/m/d H:i:s')]);
  }

  /* 商品状态 */
  static function Status(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $sku = self::JsonName($json, 'sku');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=> 4001]);
    if(empty($sku) || !is_array($sku)) return self::GetJSON(['code'=> 4000]);
    // 数据
    $list = [];
    $sku = array_values(array_unique($sku));
    $pname = Goods::GoodsInfoAll($sku, 'pname');
    $admin = AdminToken::Token($token);
    foreach($sku as $v) $list[$v] = 1;
    // 商品资料
    $m = new ErpGoodsInfoM();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id');
    $m->Where('sku_id in("' . implode('","', $sku) . '")');
    $all = $m->Find();
    foreach($all as $v) $list[$v['sku_id']] = 2;
    if($admin->type==9) return self::GetJSON(['code'=>0, 'data'=>$list]);
    // 入库
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id');
    $m->Where('status=1 AND sku_id in("' . implode('","', $sku) . '")');
    $all = $m->Find();
    foreach($all as $v) $list[$v['sku_id']] = 3;
    // 发货
    $m = new ErpOrderShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id');
    $m->Where('sku_id in("' . implode('","', $sku) . '")');
    $all = $m->Find();
    foreach($all as $v) $list[$v['sku_id']] = 4;
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>$list]);
  }

  /* 选项 */
  static function GetSelect(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg != '') return self::GetJSON(['code'=> 4001]);
    // 标签
    $labels = [];
    foreach(Status::Goods('labels') as $v) $labels[] = ['label'=> $v, 'value'=> $v];
    // 分类
    $category_name = [];
    $all = Category::GetList();
    foreach($all as $v) $category_name[] = ['label'=> $v['name'], 'value'=> $v['name']];
    // 品牌
    $brand = [];
    $all = Brand::GetList();
    foreach($all as $k=> $v) $brand[] = ['label'=> $k, 'value'=> $k];
    // 返回
    return self::GetJSON(['code'=> 0, 'data'=> [
      'labels'=> $labels,
      'category'=> $category_name,
      'brand'=> $brand,
    ]]);
  }
}
