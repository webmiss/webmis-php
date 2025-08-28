<?php

namespace Data;

use Service\Base;
use Service\Data as sData;
use Service\Logs;
use Library\Safety;
use Library\Aliyun\Oss;
use Util\Util;
use Data\Brand;
use Data\Partner;
use Data\Goods as GoodsD;
use Model\ErpGoodsInfo;
use Model\ErpPurchaseStock;
use Model\ErpPurchaseInShow;
use Model\ErpPurchaseOutShow;
use Model\ErpOrderShow;
use Model\ErpPurchaseAllocateShow;

/* 商品 */
class Goods extends Base {

  static $category = [];        // 分类
  private static $Brand = [];   // 品牌

  /* 限制成本价 */
  static $exclude_user = [1];
  static function isPrice(object $user, array $rule=[]): bool {
    // 用户: 无限制
    if(in_array($user->uid, self::$exclude_user)) return true;
    // 角色: 系统开发、管理员、财务部
    if(isset($user->role) && in_array($user->role, [37, 4, 3])) return true;
    // 采购员
    if(isset($rule['owner']) && $rule['owner']===$user->name) return true;
    return false;
  }

  /* 商品-信息 */
  static function GoodsInfo(string $sku_id, string $type='data', array $columns=[]): array|string {
    $pname = sData::PartitionSku($sku_id, [0, 1]);
    if(!$pname) $pname = 'p'.substr(date('Ymd', strtotime('-1 month')), 2, 4).','.'p'.substr(date('Ymd'), 2, 4);
    if($type=='pname') return $pname;
    $data = self::GetGoodsData([$sku_id], $pname, $columns);
    return $data?$data[0]:[];
  }
  /* 商品-信息多条 */
  static function GoodsInfoAll(array $sku, string $type='data', array $limit=[0, 1], array $columns=[]): array|string {
    $tmp = [];
    $sku = array_unique($sku);
    foreach ($sku as $sku_id) {
      $pname = sData::PartitionSku($sku_id, $limit);
      if(!$pname) {
        $tmp=[]; break;
      }
      $tmp = array_merge($tmp, explode(',', $pname));
    }
    // 分区
    if($tmp) {
      $tmp = array_unique($tmp);
      sort($tmp);
      $tmp = array_values($tmp);
    }
    $pname = implode(',', $tmp);
    if($type=='pname') return $pname;
    // 数据
    $list = self::GetGoodsData($sku, $pname, $columns);
    $data = [];
    foreach ($list as $v) $data[$v['sku_id']] = $v;
    return $data;
  }
  private static function getGoodsData(array $sku, string $partition='', array $columns=[]): array {
    if(empty($sku)) return [];
    // 字段
    if(!$columns) $columns = [
      'id',
      'img',
      'sku_id',
      'i_id',
      'owner',
      'name',
      'short_name',
      'properties_value',
      'cost_price',
      'purchase_price',
      'supply_price',
      'supplier_price',
      'sale_price',
      'market_price',
      'other_price',
      'other_price1',
      'unit',
      'weight',
      'num',
      'category',
      'labels',
      'brand',
      'supplier_id',
      'supplier_name',
      'ratio',
      'FROM_UNIXTIME(ctime) as ctime',
      'FROM_UNIXTIME(utime) as utime',
    ];
    // 商品资料
    $m = new ErpGoodsInfo();
    if($partition) $m->Partition($partition);
    $m->Columns(...$columns);
    $m->Where('sku_id in("'.implode('","', $sku).'")');
    $m->Order('id DESC');
    return $m->Find();
  }

  /* 商品-资料认证 */
  static function GoodsInfoVerify(array $data): string|array {
    if(!$data) return '无数据!';
    // 是否重复
    $sku = $brand = [];
    foreach ($data as $v) {
      $sku[] = strtoupper(Util::Trim((string)$v['sku_id']));
      if(!isset($v['brand'])) return '品牌不能为空!';
      $brand[$v['brand']] = 1;
    }
    $sku = array_unique($sku);
    if(count($data) != count($sku)) return '商品编码不能重复!';
    if(count($brand)>1) return '['.implode(',', array_keys($brand)).']只能1个品牌!';
    // 品牌
    if(empty(self::$Brand)) self::$Brand = Brand::GetList();
    // 分类
    self::$category = [];
    $all = Category::GetList();
    foreach($all as $v) self::$category[] = $v['name'];
    // 验证
    $list = [];
    foreach ($data as $v) {
      // 商品编码
      $sku_id = isset($v['sku_id'])?strtoupper(Util::Trim($v['sku_id'])):'';
      if(!Safety::Test('^[A-Z0-9]{3,16}$', $sku_id)) return '[ '.$sku_id.' ]商品编码: 3～16位大写英文、数字!';
      // 暗码
      $short_name = isset($v['short_name'])?Util::Trim($v['short_name']):'';
      if(mb_strlen($short_name)<0 || mb_strlen($short_name)>32) return '['.$short_name.']暗码: 2～32位字符!';
      // 商品名称
      $name = isset($v['name'])?Util::Trim($v['name']):'';
      if(mb_strlen($name)<2 || mb_strlen($name)>32) return '['.$name.']商品名称: 2～32位字符!';
      // 商品分类
      $category = isset($v['category'])?Util::Trim($v['category']):'';
      if(!in_array($category, self::$category)) return '['.$category.']分类: “'.implode(',', self::$category).'”';
      // 颜色及规格
      $properties_value = isset($v['properties_value'])?Util::Trim($v['properties_value']):'';
      if(mb_strlen($properties_value)<2 || mb_strlen($properties_value)>32) return '['.$properties_value.']颜色及规格: 2～32位字符!';
      // 单位
      $unit = isset($v['unit'])?Util::Trim($v['unit']):'';
      if(mb_strlen($unit)<0 || mb_strlen($unit)>2) return '['.$unit.']单位: 0～2位字符!';
      // 重量
      $weight = isset($v['weight'])?Util::Trim($v['weight']):0.00;
      if(!is_numeric($v['weight'])) return '['.$weight.']重量: 只能为数字';
      // 成本价
      $cost_price = isset($v['cost_price'])?(float)$v['cost_price']:0.00;
      if($cost_price<0 || $cost_price>9999999999) return '['.$cost_price.']成本价: 0～9999999999元';
      // 采购价
      $purchase_price = isset($v['purchase_price'])?(float)$v['purchase_price']:0.00;
      if($purchase_price<0 || $purchase_price>9999999999) return '['.$purchase_price.']采购价: 0～9999999999元';
      // 供应链价
      $supply_price = isset($v['supply_price'])?(float)$v['supply_price']:0.00;
      if($supply_price<0 || $supply_price>9999999999) return '['.$supply_price.']供应链价: 0～9999999999元';
      // 人民币采购价
      $supplier_price = isset($v['supplier_price'])?(float)$v['supplier_price']:0.00;
      if($supplier_price<0 || $supplier_price>9999999999) return '['.$supplier_price.']人民币采购价: 0～9999999999元';
      // 标签价
      $sale_price = isset($v['sale_price'])?(float)$v['sale_price']:0.00;
      if($sale_price<0 || $sale_price>9999999999) return '['.$sale_price.']标签价: 0～9999999999元';
      // 吊牌价
      $market_price = isset($v['market_price'])?(float)$v['market_price']:0.00;
      if($market_price<0 || $market_price>9999999999) return '['.$market_price.']吊牌价: 0～9999999999元';
      // 数量
      $num = isset($v['num'])?(int)$v['num']:1;
      if($num<0 || $num>9999999999) return '['.$num.']数量: 0～9999999999';
      // 区域
      $labels = isset($v['labels'])?Util::Trim($v['labels']):'';
      if(!in_array($labels, Status::Goods('labels'))) return '['.$labels.']区域: “'.implode(',', Status::Goods('labels')).'”';
      // 品牌
      $brand = isset($v['brand'])?Util::Trim($v['brand']):'';
      if(!isset(self::$Brand[$brand])) return '['.$brand.']品牌: “'.implode(',', array_keys(self::$Brand)).'”';
      $rule = self::$Brand[$brand]['rule']?self::$Brand[$brand]['rule']:'';
      if($rule && !preg_match($rule, $v['sku_id'])) {
        // 提取
        $rule = strtr($rule, ['/^' => '', '\d' => '', '[A-Z0-9]' => '', '$/' => '']);
        // 字母
        preg_match_all('/\(([A-Z|\|]+)\)/', $rule, $arr);
        $letter = $arr[0][0];
        $letter_arr = explode('|', trim($letter, '()'));
        // 数字
        preg_match_all('/\{\d,\d\}/', $rule, $arr);
        $digit = $arr[0];
        foreach ($digit as $len) {
          if($len=='{6,6}') {
            $rule = str_replace($len, substr(date('Ymd'), 2, 6), $rule);
          } else {
            $tmp = explode(',', trim($rule, '{}'));
            $rule = str_replace($len, str_pad(1, $tmp[1] - 1, '0', STR_PAD_LEFT), $rule);
          }
        }
        // 案例
        $arr = [];
        foreach ($letter_arr as $key) $arr[] = str_replace($letter, $key, $rule).'';
        return '[ '.$v['brand'].' ]编码规则: '.implode(', ', $arr);
      }
      // 采购员
      $owner = isset($v['owner'])?Util::Trim($v['owner']):'';
      if(mb_strlen($owner)<2 || mb_strlen($owner)>16) return '['.$owner.']采购员: 2～16位字符!';
      // 款式编码
      $i_id = isset($v['i_id'])?Util::Trim($v['i_id']):'';
      if(mb_strlen($i_id)<2 || mb_strlen($i_id)>32) return '['.$i_id.']款式编码: 2～32位字符!';
      // 供应商名称
      $supplier_name = isset($v['supplier_name'])?Util::Trim($v['supplier_name']):'';
      if(mb_strlen($supplier_name)<2 || mb_strlen($supplier_name)>32) return '['.$supplier_name.']供应商: 2～32位字符!';
      // if(!Safety::Test('^.*\d{4}$', $supplier_name)) return '['.$supplier_name.']供应商: 姓名+手机号后4位!';
      // 列表
      $list[$sku_id] = [
        'sku_id' => $sku_id,
        'name' => $name,
        'short_name' => $short_name,
        'category' => $category,
        'properties_value' => $properties_value,
        'unit' => $unit,
        'weight' => $weight,
        'cost_price' => $cost_price,
        'purchase_price' => $purchase_price,
        'supply_price' => $supply_price,
        'supplier_price' => $supplier_price,
        'sale_price' => $sale_price,
        'market_price' => $market_price,
        'num' => $num,
        'labels' => $labels,
        'brand' => $brand,
        'owner' => $owner,
        'i_id' => $i_id,
        'supplier_name' => $supplier_name,
      ];
    }
    return $list;
  }

  /* 商品-资料比较 */
  static function GoodsInfoDiff(array $now, array $old): string {
    $msg = '';
    $columns = ['sku_id', 'i_id', 'name', 'short_name', 'properties_value', 'cost_price', 'purchase_price', 'supply_price', 'supplier_price', 'sale_price', 'market_price', 'other_price', 'other_price1', 'unit', 'weight', 'num', 'labels', 'labels', 'category', 'brand', 'owner', 'supplier_name'];
    foreach ($columns as $name) {
      if(isset($now[$name]) && isset($old[$name]) && $now[$name] != $old[$name]) $msg .= ' ['.$old[$name].']>['.$now[$name].']';
    }
    return $msg;
  }

  /* 是否进行中 */
  static function IsAfoot(array $sku, int $wms_co_id=0, $type='all'): array {
    $res = [];
    // 分区
    $pname = date('d')>=15?'p'.substr(date('Ym'), 2, 4):'p'.substr(date('Ym', strtotime('-1 month')), 2, 4).',p'.substr(date('Ym'), 2, 4);
    if($type==='all' || $type==='in') {
      // 采购入库
      $m = new ErpPurchaseInShow();
      $m->Partition($pname);
      $m->Columns('pid', 'num', 'sku_id');
      $m->Where('sku_id in("'.implode('","', $sku).'") AND state="0"'.($wms_co_id?' AND wms_co_id='.$wms_co_id:''));
      $all = $m->Find();
      if($all) {
        $res['num'] = (int)$all[0]['num'];
        $res['msg'] = '[ '.$all[0]['sku_id'].' ]已存在，入库单( '.$all[0]['pid'].' )';
        // 进行中
        $tmp = [];
        foreach ($all as $v) $tmp[(string)$v['sku_id']] = $v['num'];
        $res['list'] = isset($res['list'])?array_merge($res['list'], $tmp):$tmp;
      }
    }
    if($type==='all' || $type==='allocate') {
      // 调拨单
      $m = new ErpPurchaseAllocateShow();
      $m->Partition($pname);
      $m->Columns('pid', 'num', 'sku_id');
      $m->Where('sku_id in("'.implode('","', $sku).'") AND state="0"'.($wms_co_id?' AND go_co_id='.$wms_co_id:''));
      $all = $m->Find();
      if($all) {
        $res['num'] = (int)$all[0]['num'];
        $res['msg'] = '[ '.$all[0]['sku_id'].' ]已存在，调拨单( '.$all[0]['pid'].' )';
        // 进行中
        $tmp = [];
        foreach ($all as $v) $tmp[(string)$v['sku_id']] = $v['num'];
        $res['list'] = isset($res['list'])?array_merge($res['list'], $tmp):$tmp;
      }
    }
    if($type==='all' || $type==='out') {
      // 采购退货
      $m = new ErpPurchaseOutShow();
      $m->Partition($pname);
      $m->Columns('pid', 'num', 'sku_id');
      $m->Where('sku_id in("'.implode('","', $sku).'") AND state="0"'.($wms_co_id?' AND wms_co_id='.$wms_co_id:''));
      $all = $m->Find();
      if($all) {
        $res['num'] = (int)$all[0]['num'];
        $res['msg'] = '[ '.$all[0]['sku_id'].' ]已存在，退货单( '.$all[0]['pid'].' )';
        // 进行中
        $tmp = [];
        foreach ($all as $v) $tmp[(string)$v['sku_id']] = $v['num'];
        $res['list'] = isset($res['list'])?array_merge($res['list'], $tmp):$tmp;
      }
    }
    // 返回
    return $res;
  }

  /* 库存数量 */
  static function IsStock(string $sku_id, int $wms_co_id): int {
    // 查询
    $m = new ErpPurchaseStock();
    $m->Columns('num');
    $m->Where('sku_id=? AND wms_co_id=?', $sku_id, $wms_co_id);
    $one = $m->FindFirst();
    return $one?(int)$one['num']:0;
  }
  static function IsStockAll(array $sku, int $wms_co_id): array {
    // 查询
    $m = new ErpPurchaseStock();
    $m->Columns('sku_id', 'num');
    $m->Where('wms_co_id=? AND sku_id in("'.implode('","', $sku).'")', $wms_co_id);
    $all = $m->Find();
    $list = [];
    foreach ($all as $v) $list[$v['sku_id']] = (int)$v['num'];
    return $list;
  }

  /* 是否相同SKU */
  static function IsSkuSame(array $sku): string {
    $tmp = array_count_values($sku);
    foreach ($tmp as $k => $num) {
      if($num>1) return $k;
    }
    return '';
  }

  /* 商品图片-文件转Base64 */
  static function GoodsImgFile(string $sku_id, array $file): string {
    $ct = file_get_contents($file['tmp_name']);
    $base64 = 'data:image/jpeg;base64,'.base64_encode($ct);
    return self::GoodsImg($sku_id, $base64);
  }
  /* 商品图片-上传Base64 */
  static function GoodsImg(string $sku_id, string $base64): string {
    // 限制格式
    $extAll = [
      'data:image/jpeg;base64' => 'jpg',
      'data:image/png;base64' => 'png',
    ];
    $ct = explode(',', $base64);
    $ext = $extAll[$ct[0]];
    if(!$ext) return '只能上传JPG、PNG格式图片!';
    // OSS
    $file = 'img/sku/'.$sku_id.'.jpg';
    $res = Oss::PutObject($file, $ct[1]);
    if(!$res) return '上传失败!';
    // 图片状态
    $m = new ErpGoodsInfo();
    $m->Set(['img' => 1]);
    $m->Where('sku_id=?', $sku_id);
    return $m->Update()?$file:'';
  }

  /* 商品图片-状态 */
  static function GoodsImgState(array $param = []): string | bool {
    $res = '';
    // 参数
    $param = array_merge([
      'type' => 'update',     // 类型: update, remove
      'sku_id' => '',         // 商品编码
      'user_uid' => '',       // 用户ID
      'user_name' => '',      // 用户姓名
      'source' => '',         // 来源: PC, API
      'base64' => '',         // base64
      'file' => [],           // 文件
    ], $param);
    // 类型
    if($param['type']=='update') {
      $img = 1;
      // 图片内容
      if($param['base64']) {
        $res = self::GoodsImg($param['sku_id'], $param['base64']);
      } elseif($param['file']) {
        $res = self::GoodsImgFile($param['sku_id'], $param['file']);
      }
    } elseif($param['type']=='remove') {
      $img = 0;
      // 清理图片
      $res = Oss::DeleteObject('img/sku/'.$param['sku_id'].'.jpg');
    }
    // 商品资料
    $m = new ErpGoodsInfo();
    $m->Set(['img' => $img]);
    $m->Where('sku_id=?', $param['sku_id']);
    $m->Update();
    // 更新明细
    if(!Goods::GoodsUpdateShow([$param['sku_id']], ['img'=>$img])) return $res;
    // 日志
    if(!$param['user_uid']) return $res;
    Logs::Goods([
      'ctime' => time(),
      'operator_id' => $param['user_uid'],
      'operator_name' => $param['user_name'],
      'sku_id' => $param['sku_id'],
      'content' => ($img==1?'更新图片':'移除图片').': '.$param['sku_id'].' 来源:'.$param['source']
    ]);
    return $res;
  }

  /* 更新明细 */
  static function GoodsUpdateShow(array $sku, array $data): bool {
    // 条件
    if(count($sku)==1) {
      $where = 'sku_id="'.$sku[0].'"';
    } else {
      $where = 'sku_id in("'.implode('","', $sku).'")';
    }
    // 订单
    $m = new ErpOrderShow();
    $m->Set($data);
    $m->Where($where.' AND state_order<>"3"');
    $res = $m->Update();
    if(!$res) return false;
    // 采购员、供应商
    $value = [];
    if(isset($data['owner'])) $value['owner']=$data['owner'];
    if(isset($data['supplier_name'])) $value['supplier_name']=$data['supplier_name'];
    if($value) {
      // 入库
      $m = new ErpPurchaseInShow();
      $m->Set($value);
      $m->Where($where);
      $m->Update();
      // 退货
      $m = new ErpPurchaseOutShow();
      $m->Set($value);
      $m->Where($where);
      $m->Update();
      // 库存
      if(isset($data['category'])) $value['category']=$data['category'];
      $m = new ErpPurchaseStock();
      $m->Set($value);
      $m->Where($where);
      $m->Update();
    }
    return true;
  }

  /* 商品-流向 */
  static function GoodsWork(string $sku_id, array $limt = []): array|string {
    // 分区
    $info = GoodsD::GoodsInfo($sku_id);
    if(!$info) return [[], []];
    $info['num'] = '';
    $info['img'] = $info['img']?sData::ImgGoods($info['sku_id'], false):'';
    $pname = sData::PartitionSku($sku_id, $limt);
    if(!$info) return $info;
    // 统计
    $total = [];
    // 库存
    $m = new ErpPurchaseStock();
    $m->Columns('sum(num) AS num');
    $m->Where('sku_id=?', $sku_id);
    $one = $m->FindFirst();
    $total['total'] = $one?(int)$one['num']:0;
    // 数据
    $list = [];
    // 数据-入库
    $m = new ErpPurchaseInShow();
    $m->Table($pname?'erp_purchase_show_in PARTITION('.$pname.') as a':'erp_purchase_show_in as a');
    $m->LeftJoin('erp_purchase_in as b', 'a.pid=b.id');
    $m->Columns('a.type', 'a.pid', 'b.wms_co_id', 'a.num', 'a.state', 'FROM_UNIXTIME(a.ctime) as ctime', 'FROM_UNIXTIME(b.utime) as utime', 'b.creater_name AS creater', 'b.operator_name AS operator', 'b.remark');
    $m->Where('a.sku_id=?', $sku_id);
    $all = $m->Find();
    $list = array_merge($list, $all);
    $total['num_in'] = 0;
    foreach ($all as $v) $total['num_in'] += $v['num'];
    // 数据-退货
    $m = new ErpPurchaseOutShow();
    $m->Table($pname?'erp_purchase_show_out PARTITION('.$pname.') as a':'erp_purchase_show_out as a');
    $m->LeftJoin('erp_purchase_out as b', 'a.pid=b.id');
    $m->Columns('a.type', 'a.pid', 'b.wms_co_id', 'a.num', 'a.state', 'FROM_UNIXTIME(a.ctime) as ctime', 'FROM_UNIXTIME(b.utime) as utime', 'b.creater_name AS creater', 'b.operator_name AS operator', 'b.remark');
    $m->Where('a.sku_id=?', $sku_id);
    $all = $m->Find();
    $list = array_merge($list, $all);
    $total['num_out'] = 0;
    foreach ($all as $v) $total['num_out'] += $v['num'];
    // 数据-调拨
    $m = new ErpPurchaseAllocateShow();
    $m->Table($pname?'erp_purchase_show_allocate PARTITION('.$pname.') as a':'erp_purchase_show_allocate as a');
    $m->LeftJoin('erp_purchase_allocate as b', 'a.pid=b.id');
    $m->Columns('a.type', 'a.pid', 'b.go_co_id', 'b.link_co_id', 'a.num', 'a.ratio', 'a.state', 'FROM_UNIXTIME(a.ctime) as ctime', 'FROM_UNIXTIME(b.utime) as utime', 'b.creater_name AS creater', 'b.operator_name AS operator', 'b.remark');
    $m->Where('a.sku_id=?', $sku_id);
    $all = $m->Find();
    $list = array_merge($list, $all);
    $total['allocate'] = 0;
    foreach ($all as $v) $total['allocate'] += $v['num'];
    // 数据-发货
    $m = new ErpOrderShow();
    $m->Table($pname?'erp_purchase_show_order PARTITION('.$pname.') as a':'erp_purchase_show_order as a');
    $m->LeftJoin('erp_order_out as b', 'a.pid=b.id');
    $m->Columns('a.type','a.pid','a.wms_co_id','a.num','a.ratio','a.state','FROM_UNIXTIME(a.ctime) as ctime','FROM_UNIXTIME(a.utime) as utime','a.operator_name AS creater','a.operator_name AS operator','b.remark');
    $m->Where('a.type="3" AND a.sku_id=?', $sku_id);
    $all = $m->Find();
    $list = array_merge($list, $all);
    $total['num_order'] = 0;
    foreach ($all as $v) $total['num_order'] += $v['num'];
    // 数据-售后
    $m = new ErpOrderShow();
    $m->Table($pname?'erp_purchase_show_order PARTITION('.$pname.') as a':'erp_purchase_show_order as a');
    $m->LeftJoin('erp_order_refund as b', 'a.pid=b.id');
    $m->Columns('a.type', 'a.pid', 'a.wms_co_id', 'a.num', 'a.state', 'FROM_UNIXTIME(a.ctime) as ctime', 'FROM_UNIXTIME(a.utime) as utime', 'a.operator_name AS creater', 'a.operator_name AS operator', 'b.remark');
    $m->Where('a.type="4" AND a.sku_id=?', $sku_id);
    $all = $m->Find();
    $list = array_merge($list, $all);
    $total['num_refund'] = 0;
    foreach ($all as $v) $total['num_refund'] += $v['num'];
    // 数据-其它
    $m = new ErpOrderShow();
    $m->Table($pname?'erp_purchase_show_order PARTITION('.$pname.') as a':'erp_purchase_show_order as a');
    $m->LeftJoin('erp_other_inout as b', 'a.pid=b.id');
    $m->Columns('a.type', 'a.pid', 'b.wms_co_id', 'a.num', 'a.state', 'FROM_UNIXTIME(a.ctime) as ctime', 'FROM_UNIXTIME(a.utime) as utime', 'b.creator_name AS creater', 'b.operator_name AS operator', 'b.remark');
    $m->Where('a.type in("5", "6") AND a.sku_id=?', $sku_id);
    $all = $m->Find();
    $list = array_merge($list, $all);
    $total['other_out'] = $total['other_in'] = 0;
    foreach ($all as $v) {
      if($v['type']=='5') $total['other_out'] += $v['num'];
      elseif($v['type'=='6']) $total['other_in'] += $v['num'];
    }
    // 数据
    $partner_all = Partner::GetList();
    foreach ($list as $k => $v) {
      // 商品信息
      $v = array_merge($info, $v);
      // 类型
      $v['type_name'] = isset(Status::Goods('type_name')[$v['type']])?Status::Goods('type_name')[$v['type']]:'';
      // 仓库
      if($v['type']=='2') $v['warehouse'] = $partner_all[$v['go_co_id']]['name'].'>'.$partner_all[$v['link_co_id']]['name'];
      else $v['warehouse'] = isset($v['wms_co_id'])?$partner_all[$v['wms_co_id']]['name']:'';
      // 成本价
      if(in_array($v['brand'], ['礼品', '物资'])) $v['sale_price'] = $v['cost_price'];
      $v['cost_price'] = '0.00';
      // 客服备注
      if(in_array($v['type'], [3, 4])) $v['remark'] = '-';
      // 折扣
      $v['ratio'] = isset($v['ratio'])?$v['ratio']:'1.00';
      // 数据
      $list[$k] = $v;
    }
    // 商品资料
    $info['type'] = '-1';
    $info['type_name'] = Status::Goods('type_name')[$info['type']];
    $info['pid'] = $info['id'];
    $info['warehouse'] = '';
    $info['state'] = '1';
    $info['creater'] = 'SYS';
    $info['operator'] = 'SYS';
    $info['remark'] = '';
    $goods[] = $info;
    $list = array_merge($list, $goods);
    // 排序
    $list = Util::AarraySort($list, 'ctime');
    return [$total, $list];
  }

}
