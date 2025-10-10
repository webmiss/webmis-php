<?php
namespace App\Admin;

use Config\Env;
use Service\AdminToken;
use Service\Base;
use Service\Logs;
use Service\Data as sData;
use Library\Export;
use Data\Status;
use Data\Brand;
use Data\Partner;
use Data\Goods;
use Util\Util;
use Model\ErpPurchaseIn as ErpPurchaseInM;
use Model\ErpPurchaseInShow;

class ErpPurchaseIn extends Base {

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
    $m->Order($order?:'status, ctime DESC');
    $list = $m->Find();
    // 数据
    self::$partner_name = Partner::GetList();
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
    $where = [];
    $admin = AdminToken::Token($token);
    // 限制-品牌
    if($admin->brand){
      $brand = explode(',', $admin->brand);
      $where[] = '(creator_id='.$admin->uid.' OR brand in("'.implode('","', $brand).'"))';
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
    if($remark!='') $where[] = 'remark like "%'.$remark.'%"';
    // 返回
    return implode(' AND ', $where);
  }
  /* 搜索条件-PID */
  static function getPid(string $sku_id, $start, $end) {
    // 条件
    $arr = array_values(array_filter(explode(' ', $sku_id)));
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

  /* 保存 */
  static function Save(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || !is_array($data)) return self::GetJSON(['code'=> 4000]);
    // 数据
    $admin = AdminToken::Token($token);
    $id = isset($data['id'])&&$data['id']?$data['id']:'';
    $param['type'] = isset($data['type'])&&$data['type']?$data['type'][0]:'';
    $param['wms_co_id'] = isset($data['wms_co_id'])&&$data['wms_co_id']?$data['wms_co_id'][0]:'';
    $param['brand'] = isset($data['brand'])&&$data['brand']?$data['brand'][0]:'';
    $param['remark'] = isset($data['remark'])?trim($data['remark']):'';
    if($param['type']=='' || $param['wms_co_id']=='' || $param['brand']=='') return self::GetJSON(['code'=>4000]);
    // 类型
    if(!$id) {
      // 添加
      $param['ctime'] = time();
      $param['utime'] = time();
      $param['creator_id'] = $admin->uid;
      $param['creator_name'] = $admin->name;
      $m = new ErpPurchaseInM();
      $m->Values($param);
      if($m->Insert()) {
        $id = $m->GetID();
        Logs::Goods([
          'ctime'=>time(),
          'operator_id'=> $admin->uid,
          'operator_name'=> $admin->name,
          'sku_id'=> $id,
          'content'=> '创建入库单: '.$id
        ]);
        return self::GetJSON(['code'=>0]);
      } else {
        return self::GetJSON(['code'=>5000]);
      }
    }
    // 单据
    $m = new ErpPurchaseInM();
    $m->Columns('id');
    $m->Where('status="0" AND id=?', $id);
    $info = $m->FindFirst();
    if(!$info) return self::GetJSON(['code'=>4000, 'msg'=>'当前状态不可用!']);
    // 编辑
    $param['utime'] = time();
    $param['operator_id'] = $admin->uid;
    $param['operator_name'] = $admin->name;
    $m = new ErpPurchaseInM();
    $m->Set($param);
    $m->Where('id=?', $id);
    if($m->Update()) {
      // 日志
      Logs::Goods([
        'ctime'=>time(),
        'operator_id'=> $admin->uid,
        'operator_name'=> $admin->name,
        'sku_id'=> $id,
        'content'=> '更新入库单: '.$id
      ]);
      return self::GetJSON(['code'=>0]);
    } else {
      return self::GetJSON(['code'=>5000]);
    }
  }

  /* 删除 */
  static function Del(): string {
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
    $ids = implode(',', $data);
    // 明细
    $m1 = new ErpPurchaseInShow();
    $m1->Where('pid in('.$ids.')');
    // 单据
    $m2 = new ErpPurchaseInM();
    $m2->Where('id in('.$ids.') AND status=0');
    if($m1->Delete() && $m2->Delete()){
      // 日志
      $admin = AdminToken::Token($token);
      $pids = explode(',', $ids);
      foreach($pids as $pid){
        Logs::Goods([
          'ctime'=>time(),
          'operator_id'=> $admin->uid,
          'operator_name'=> $admin->name,
          'sku_id'=> $pid,
          'content'=> '删除入库单: '.$pid
        ]);
      }
      return self::GetJSON(['code'=>0]);
    }
    return self::GetJSON(['code'=>5000]);
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
    // 单据
    $m = new ErpPurchaseInM();
    $m->Columns('id', 'ctime', 'utime', 'brand');
    $m->Where('status=0 AND id in('.$id.')');
    $info = $m->Find();
    if(!$info) return self::GetJSON(['code'=>4000, 'msg'=>'当前状态不可用!']);
    // 品牌
    foreach($info as $v1) {
      $pname = sData::PartitionName($v1['ctime'], $v1['utime']);
      $m = new ErpPurchaseInShow();
      if($pname) $m->Partition($pname);
      $m->Columns('sku_id');
      $m->Where('pid=?', $v1['id']);
      $all = $m->Find();
      $sku = [];
      foreach($all as $v2) $sku[]=$v2['sku_id'];
      $goods = Goods::GoodsInfoAll($sku, 'data', [-1, 3], ['sku_id', 'brand']);
      foreach($goods as $sku_id=>$v3) {
        if($v1['brand']!=$v3['brand']) return self::GetJSON(['code'=>4000, 'msg'=>'[ '.$sku_id.' ]品牌为“'.$v3['brand'].'”不等于“'.$v1['brand'].'”']);
      }
    }
    // 更新
    $m = new ErpPurchaseInM();
    $m->Set(['status'=>1, 'utime'=>time()]);
    $m->Where('status=0 AND id in('.$id.')');
    if($m->Update()) {
      foreach($info as $v) {
        // 更新价格
        $pname = sData::PartitionName($v['ctime'], $v['utime']);
        self::goodsUpdatePrice($admin, $pname, $v['id']);
        // 日志
        Logs::Goods([
          'ctime'=>time(),
          'operator_id'=> $admin->uid,
          'operator_name'=> $admin->name,
          'sku_id'=> $v['id'],
          'content'=> '推送入库单: '.$v['id']
        ]);
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
    $ids = implode(',', $data);
    // 更新
    $m = new ErpPurchaseInM();
    $m->Set(['status'=>0, 'utime'=>time()]);
    $m->Where('status=1 AND id in('.$ids.')');
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
      '单号', '类型', '入库仓库', '图片', '商品编码', '暗码', '商品名称', '颜色及规格', '供应链价(元)', '供应链折扣', '标签价(元)', '标签折扣', '吊牌价(W)', '吊牌折扣', '数量', '折扣', '单位', '重量', '标签', '商品分类', '品牌', '款式编码', '采购员', '状态', '制单员', '操作员', '创建时间', '修改时间', '备注'
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
        '&nbsp;'.$tmp['short_name']?$tmp['short_name']:'-',
        $tmp['name']?$tmp['name']:'-',
        '&nbsp;'.$tmp['properties_value']?$tmp['properties_value']:'-',
        $tmp['supply_price']>0?round($tmp['supply_price']*$v['num']*($tmp['ratio']<1?$tmp['ratio']:$tmp['ratio_supply']), 2):'-',
        $tmp?$tmp['ratio_supply']:1.00,
        $tmp['sale_price']>0?round($tmp['sale_price']*$v['num']*($tmp['ratio']<1?$tmp['ratio']:$tmp['ratio_sale']), 2):'-',
        $tmp?$tmp['ratio_sale']:1.00,
        $tmp['market_price']>0?round($tmp['market_price']*$v['num']*($tmp['ratio']<1?$tmp['ratio']:$tmp['market_price']), 2):'-',
        $tmp?$tmp['ratio_market']:1.00,
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
    $file_name = 'PurchaseIn_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
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
    self::$partner_name = Partner::GetList(['type=0', 'status=1']);
    foreach(self::$partner_name as $k=>$v) {
      $tmp = ['label'=>$v['name'], 'value'=>$k, 'info'=>$v['status']?true:false];
      if($partner_perm) {
        if(in_array($k, $partner_perm)) $partner_name[] = $tmp;
      } else $partner_name[] = $tmp;
    }
    //  品牌
    $brand_name = [];
    self::$brand_name = Brand::GetList();
    foreach(self::$brand_name as $k=>$v) {
      $tmp = ['label'=>$v['name'], 'value'=>$k, 'info'=>$v['status']?true:false];
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
      $v['ratio_supply'] = $info['ratio_supply'];
      $v['ratio_sale'] = $info['ratio_sale'];
      $v['ratio_market'] = $info['ratio_market'];
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

  /* 商品-检测 */
  static function GoodsSafety(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $wms_co_id = self::JsonName($json, 'wms_co_id');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($wms_co_id) || empty($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 数据
    $list = [];
    foreach($data as $v) {
      $sku_id = strtoupper(Util::Trim($v['sku_id']));
      $num = isset($v['num'])?(int)$v['num']:1;
      $list[$sku_id]=['sku_id'=>$sku_id, 'num'=>$num, 'status'=>true, 'status_name'=>'可用'];
    }
    // 是否安全
    $is_safety = true;
    // 是否进行中
    $sku = array_keys($list);
    $res = Goods::IsAfoot($sku);
    $ing = $res?$res['list']:[];
    foreach($ing as $k=>$v) {
      $is_safety = false;
      $list[$k]['status'] = false;
      $list[$k]['status_name'] = '进行中';
    }
    // 返回
    return $is_safety?self::GetJSON(['code'=>0]):self::GetJSON(['code'=>5000, 'msg'=>'请核对商品', 'data'=>array_values($list)]);
  }

  /* 商品-添加 */
  static function GoodsAdd(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $id = self::JsonName($json, 'id');
    $ctime = self::JsonName($json, 'ctime');
    $wms_co_id = self::JsonName($json, 'wms_co_id');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($id) || empty($ctime) || empty($wms_co_id) || empty($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 状态
    $m = new ErpPurchaseInM();
    $m->Columns('id');
    $m->Where('status=0 AND id=?', $id);
    $one = $m->FindFirst();
    if(!$one) return self::GetJSON(['code'=>4000, 'msg'=>'该状态不可用!']);
    // 数据
    $msg = '';
    $list = $values = $err = [];
    $admin = AdminToken::Token($token);
    foreach($data as $v) {
      if(!isset($v['sku_id'])) {
        $msg = '必须填写商品编码'; continue;
      }
      $sku_id = strtoupper(Util::Trim($v['sku_id']));
      $num = isset($v['num'])?(int)$v['num']:1;
      $list[$sku_id]=['num'=>$num, 'ctime'=>date('Y-m-d H:i:s'), 'utime'=>date('Y-m-d H:i:s'), 'operator_name'=>$admin->name];
    }
    if(!$list) return self::GetJSON(['code'=>5000, 'msg'=>$msg, 'data'=>$list]);
    // 检测: 进行中
    $sku = array_keys($list);
    list($res_list, $res_msg) = Goods::IsSafety($sku, ['afoot'=>'all']);
    if($res_list) {
      $msg = $res_msg;
      $err = array_merge($err, $res_list);
      foreach($res_list as $v) unset($list[$v]);
    }
    if(!$list) return self::GetJSON(['code'=>5000, 'msg'=>$msg, 'data'=>$list, 'err'=>$err]);
    // 资料
    $info = Goods::GoodsInfoAll($sku);
    foreach($list as $k=>$v) {
      if(!isset($info[$k])) {
        unset($list[$k]);
        $msg = '[ '.$k.' ]无商品资料';
        $err = array_merge($err, [$k]);
        continue;
      }
      $values[] = [
        'pid'=> $id,
        'sku_id'=> $k,
        'wms_co_id'=> $wms_co_id,
        'num'=> $v['num'],
        'status'=> 0,
        'ctime'=> time(),
        'utime'=> time(),
        'pdate'=> date('Y-m-d'),
        'operator_id'=> $admin->uid,
        'operator_name'=> $admin->name,
        'owner'=> $info[$k]['owner'],
        'supplier_name'=> $info[$k]['supplier_name'],
      ];
    }
    if(!$values) return self::GetJSON(['code'=>5000, 'msg'=>$msg, 'data'=>$list, 'err'=>$err]);
    // 是否存在
    $pname = sData::PartitionName(strtotime($ctime), time());
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Columns('id', 'sku_id');
    $m->Where('pid=? AND wms_co_id=? AND sku_id in("'.implode('","', array_keys($list)).'")', $id, $wms_co_id);
    $all = $m->Find();
    $in_sku = [];
    foreach($all as $v) $in_sku[$v['sku_id']]=$v['id'];
    // 更新存在
    foreach($values as $k=>$v) {
      if(!isset($in_sku[$v['sku_id']])) continue;
      $m = new ErpPurchaseInShow();
      if($pname) $m->Partition($pname);
      $m->Set(['num'=>$v['num'], 'utime'=>time(), 'operator_id'=>$v['operator_id'], 'operator_name'=>$v['operator_name']]);
      $m->Where('id=?', $in_sku[$v['sku_id']]);
      $m->Update();
      // 移除
      unset($values[$k]);
    }
    $values = array_values($values);
    // 新增
    if($values) {
      $m = new ErpPurchaseInShow();
      $m->ValuesAll($values);
      if(!$m->Insert()) return self::GetJSON(['code'=>5000]);
    }
    // 获取ID
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Columns('id', 'sku_id');
    $m->Where('pid=? AND wms_co_id=? AND sku_id in("'.implode('","', array_keys($list)).'")', $id, $wms_co_id);
    $all = $m->Find();
    $ids = [];
    foreach($all as $v) $ids[$v['sku_id']]=$v['id'];
    // 处理
    foreach($list as $k=>$v) {
      // 日志
      Logs::Goods([
        'ctime'=>time(),
        'operator_id'=> $admin->uid,
        'operator_name'=> $admin->name,
        'sku_id'=> $k,
        'content'=> '扫码入库: '.$k.' 单号: '.$id.' 数量: '.$v['num'],
      ]);
      // 结果
      $tmp = array_merge($info[$k], $v);
      $tmp['id'] = $ids[$k];
      $tmp['img'] = $tmp['img']?sData::ImgGoods($k, false):'';
      $list[$k] = $tmp;
    }
    // 返回
    if($msg) return self::GetJSON(['code'=>5000, 'msg'=>$msg, 'data'=>$list, 'err'=>$err]);
    else return self::GetJSON(['code'=>0, 'data'=>$list]);
  }

  /* 商品-移除 */
  static function GoodsRemove(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $id = self::JsonName($json, 'id');
    $ctime = self::JsonName($json, 'ctime');
    $wms_co_id = self::JsonName($json, 'wms_co_id');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($id) || empty($ctime) || empty($wms_co_id) || empty($data)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 状态
    $m = new ErpPurchaseInM();
    $m->Columns('id');
    $m->Where('status=0 AND id=?', $id);
    $one = $m->FindFirst();
    if(!$one) return self::GetJSON(['code'=>4000, 'msg'=>'该状态不可用!']);
    // 数据
    $ids = $sku = [];
    foreach($data as $v) {
      $ids[] = $v['id'];
      $sku[$v['sku_id']] = $v['num'];
    }
    // 删除
    $m = new ErpPurchaseInShow();
    $m->Where('status=0 AND id in('.implode(',', $ids).')');
    if(!$m->Delete()) return self::GetJSON(['code'=>5000]);
    // 处理
    $admin = AdminToken::Token($token);
    foreach($sku as $sku_id=>$num) {
      // 日志
      Logs::Goods([
        'ctime'=>time(),
        'operator_id'=> $admin->uid,
        'operator_name'=> $admin->name,
        'sku_id'=> $sku_id,
        'content'=> '移除入库: '.$sku_id.' 数量: '.$num,
      ]);
    }
    // 返回
    return self::GetJSON(['code'=>0]);
  }

  /* 商品-数量 */
  static function GoodsNum(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $id = self::JsonName($json, 'id');
    $pid = self::JsonName($json, 'pid');
    $ctime = self::JsonName($json, 'ctime');
    $wms_co_id = self::JsonName($json, 'wms_co_id');
    $sku_id = self::JsonName($json, 'sku_id');
    $num = self::JsonName($json, 'num');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($id) || empty($pid) || empty($ctime) || empty($wms_co_id) || empty($sku_id) || empty($num)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 分区
    $admin = AdminToken::Token($token);
    $pname = sData::PartitionName(strtotime($ctime), time());
    // 更新
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Set(['num'=>$num, 'utime'=>time(), 'operator_id'=>$admin->uid, 'operator_name'=>$admin->name]);
    $m->Where('id=?', $id);
    if(!$m->Update()) return self::GetJSON(['code'=>5000]);
    // 日志
    Logs::Goods([
      'ctime'=>time(),
      'operator_id'=> $admin->uid,
      'operator_name'=> $admin->name,
      'sku_id'=> $sku_id,
      'content'=> '更新入库: '.$sku_id.' 数量: '.$num,
    ]);
    return self::GetJSON(['code'=>0]);
  }

  /* 商品-更新价格 */
  static function GoodsPrice(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $id = self::JsonName($json, 'id');
    $ctime = self::JsonName($json, 'ctime');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($id) || empty($ctime)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 更新价格
    $admin = AdminToken::Token($token);
    $pname = sData::PartitionName(strtotime($ctime), time());
    self::goodsUpdatePrice($admin, $pname, $id);
    return self::GetJSON(['code'=>0]);
  }
  /* 商品-更新价格 */
  private static function goodsUpdatePrice($admin, $pname, $id): void {
    // 查询
    $m = new ErpPurchaseInShow();
    if($pname) $m->Partition($pname);
    $m->Columns('sku_id', 'num');
    $m->Where('pid=?', $id);
    $all = $m->Find();
    $list = [];
    foreach($all as $v) $list[$v['sku_id']]=$v['num'];
    // 商品资料
    $info = Goods::GoodsInfoAll(array_keys($list));
    // 数据
    $num = $total = 0;
    $cost_price = $sale_price = $purchase_price = $market_price = 0;
    foreach($info as $k=>$v) {
      $cost_price += $v['cost_price']*$list[$k]*($v['ratio']<1?$v['ratio']:$v['ratio_cost']);
      $sale_price += $v['sale_price']*$list[$k]*($v['ratio']<1?$v['ratio']:$v['ratio_sale']);
      $purchase_price += $v['purchase_price']*$list[$k]*($v['ratio']<1?$v['ratio']:$v['ratio_purchase']);
      $market_price += $v['market_price']*$list[$k]*($v['ratio']<1?$v['ratio']:$v['ratio_market']);
      $num += $list[$k];
      $total++;
    }
    // 更新
    $m = new ErpPurchaseInM();
    $m->Set([
      'cost_price'=> $cost_price,
      'sale_price'=> $sale_price,
      'purchase_price'=> $purchase_price,
      'market_price'=> $market_price,
      'num'=> $num,
      'total'=> $total,
      'utime'=> time(),
      'operator_id'=> $admin->uid,
      'operator_name'=> $admin->name
    ]);
    $m->Where('id=?', $id);
    $m->Update();
  }

}