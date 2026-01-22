<?php
namespace App\Task;

use Core\Base;
use App\Service\Logs;
use App\Service\Goods;
use App\Model\ErpGoodsInfo;
use App\Model\ErpPurchaseStock;

/* 商品库存 */
class stock extends Base {

  /* 同步采购员、供应商 */
  static function syncData(): void {
    $m = new ErpPurchaseStock();
    $m->Columns('id', 'sku_id');
    $m->Where('owner="" OR supplier_name="" OR category=""');
    $m->Group('sku_id');
    $m->Limit(0, 10000);
    $all = $m->Find();
    $sku = [];
    foreach($all as $v) $sku[$v['sku_id']] = $v['id'];
    $info = Goods::GoodsInfoAll(array_keys($sku), 'data', [-1, 3], ['sku_id', 'owner', 'supplier_name', 'category']);
    foreach($info as $k=>$v) {
      $m = new ErpPurchaseStock();
      $m->Set([
        'owner'=> $v['owner'],
        'supplier_name'=> $v['supplier_name'],
        'category'=> $v['category'],
      ]);
      $m->Where('sku_id=?', $k);
      self::Print($m->Update(), $k, $sku[$k], $v['owner'], $v['supplier_name'], $v['category']);
    }
  }

  /* 清理库存 */
  static function StockClear(): void {
    $time = strtotime('-1 month', time());
    $m = new ErpPurchaseStock();
    $m->Where('num=0 AND ctime<=?', $time);
    $m->Delete();
  }

  /* 库存同步 */
  static function goods(string $data){
    $param = json_decode($data, true);
    // 参数
    $param = array_merge([
      'bizs'=>[],             // 数据
      'type'=> 'adjust',      // 方式: 增量(adjust)、覆盖(check)
      'goods'=> false,        // 是否同步商品资料
    ], $param);
    // 数据
    foreach($param['bizs'] as $wms_co_id=>$list) {
      $sku = [];
      foreach($list as $d) $sku[]=$d['sku_id'];
      // 按仓查询
      $m = new ErpPurchaseStock();
      $m->Columns('id', 'sku_id', 'wms_co_id', 'num');
      $m->Where('wms_co_id=? AND sku_id in("'.implode('","', $sku).'")', $wms_co_id);
      $all = $m->Find();
      // 组合
      $tmp = [];
      foreach($all as $v) $tmp[$v['wms_co_id'].'_'.$v['sku_id']]=['id'=>$v['id'], 'num'=>$v['num']];
      // 是否存在
      $values = [];
      foreach($list as $v) {
        // 商品库存
        if($param['goods']) {
          $m = new ErpGoodsInfo();
          $conn = $m->conn;
          $num = $v['num']>0?($param['type']=='adjust'?'num+'.$v['num']:$v['num']):($param['type']=='adjust'?'num-'.-$v['num']:$v['num']);
          $res = $m->Exec($conn, 'UPDATE erp_goods_info SET num='.$num.' WHERE sku_id="'.$v['sku_id'].'"');
          if(!$res) Logs::File('upload/erp/GoodsNum.json', ['action'=>'update', 'table'=>'erp_goods_info', 'sku_id'=>$v['sku_id'], 'num'=>$num]);
        }
        // 分仓库存
        $key = $v['wms_co_id'].'_'.$v['sku_id'];
        if(in_array($key, array_keys($tmp))){
          // 更新
          $id = $tmp[$key]['id'];
          $data = ['num'=>$param['type']=='adjust'?$tmp[$key]['num']+$v['num']:$v['num'], 'utime'=>time()];
          $m = new ErpPurchaseStock();
          $m->Set($data);
          $m->Where('id=?', $id);
          if(!$m->Update()) Logs::File('upload/erp/GoodsNum.json', ['action'=>'update', 'table'=>'erp_purchase_stock', 'id'=>$id, 'data'=>$data]);
        }else{
          $values[] = ['wms_co_id'=>$v['wms_co_id'], 'sku_id'=>$v['sku_id'], 'num'=>$v['num'], 'ctime'=>time(), 'utime'=>time()];
        }
      }
      // 添加
      if($values) {
        $m = new ErpPurchaseStock();
        $m->ValuesAll($values);
        if(!$m->Insert()) Logs::File('upload/erp/GoodsNum.json', ['action'=>'add', 'table'=>'erp_purchase_stock', 'data'=>$values]);
      }
    }
  }

}