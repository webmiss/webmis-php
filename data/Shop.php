<?php
namespace Data;

use Model\ErpBaseShop;

/* 店铺 */
class Shop {

  /* 列表 */
  static function GetList(array $where=[], array $columns=['name', 'status'], string $order_by='status DESC, sort DESC, name ASC'): array {
    $m = new ErpBaseShop();
    $m->Columns('shop_id', ...$columns);
    $m->Where(implode(' AND ', $where));
    $m->Order($order_by);
    $all = $m->Find();
    $data = [];
    foreach($all as $v){
      $data[$v['shop_id']]=$v;
    }
    return $data;
  }

  /* 店铺分仓 */
  static function GetShop($shop_id){
    $where = ['fid<>""'];
    if($shop_id) $where[]='shop_id in('.implode(',',$shop_id).')';
    $shop_all = self::GetList($where, ['name', 'fid', 'wms_live', 'wms_service', 'wms_welfare', 'wms_deliver', 'wms_video', 'wms_offline']);
    $wms_live=[]; $wms_service=[]; $wms_welfare=[]; $wms_deliver=[]; $wms_video=[]; $wms_offline=[];
    foreach($shop_all as $v){
      $wms_live = array_merge($wms_live, explode(',', $v['wms_live']));
      $wms_service = array_merge($wms_service, explode(',', $v['wms_service']));
      $wms_welfare = array_merge($wms_welfare, explode(',', $v['wms_welfare']));
      $wms_deliver = array_merge($wms_deliver, explode(',', $v['wms_deliver']));
      $wms_video = array_merge($wms_video, explode(',', $v['wms_video']));
      $wms_offline = array_merge($wms_offline, explode(',', $v['wms_offline']));
    }
    $wms_live = array_values(array_filter($wms_live));
    $wms_service = array_values(array_filter($wms_service));
    $wms_welfare = array_values(array_filter($wms_welfare));
    $wms_deliver = array_values(array_filter($wms_deliver));
    $wms_video = array_values(array_filter($wms_video));
    $wms_offline = array_values(array_filter($wms_offline));
    return [
      'wms_live'=> $wms_live?:['0'],
      'wms_service'=> $wms_service?:['0'],
      'wms_welfare'=> $wms_welfare?:['0'],
      'wms_deliver'=> $wms_deliver?:['0'],
      'wms_video'=> $wms_video?:['0'],
      'wms_offline'=> $wms_offline?:['0'],
    ];
  }

}