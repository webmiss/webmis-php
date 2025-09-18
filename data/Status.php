<?php
namespace Data;

class Status {

  /* 供应商 */
  static function Supplier(string $name): array {
    $data = [
      'city_name'=> ['平洲', '瑞丽', '四会', '缅甸'],
      'status_name'=> ['1'=>'正常', '0'=>'禁用'],
    ];
    return $data[$name];
  }

  /* 商品资料 */
  static function Goods(string $name): array {
    $data = [
      'labels'=> ['瑞丽', '平洲', '四会', '缅甸'],
      'type_name'=> ['-1'=>'资料', '0'=>'入库', '1'=>'采退', '2'=>'调拨', '3'=>'发货', '4'=>'售后', '5'=>'其它出', '6'=>'其它退'],
      'price_name'=> [
        'cost_price'=> '成本价',
        'purchase_price'=> '采购价',
        'supply_price'=> '供应链价',
        'supplier_price'=> '人民币采购价',
        'sale_price'=> '标签价',
        'market_price'=> '吊牌价',
        'order_price'=> '开单价',
        'play_price'=> '实付价',
      ],
    ];
    return $data[$name];
  }

  /* 采购入库 */
  static function PurchaseIn(string $name): array {
    $data = [
      'type_name'=> ['0'=>'普通入库', '1'=>'当天上架', '2'=>'刷货入库', '3'=>'快速入库'],
      'status_name'=> ['0'=>'待确认', '1'=>'待入库', '2'=>'完成'],
    ];
    return $data[$name];
  }

  /* 调拨单 */
  static function Allocate(string $name): array {
    $data = [
      // 调拨类型
      'type_name'=> [
        '0'=> ['label'=>'其它', 'value'=>'0', 'info'=>'全时段'],
        '1'=> ['label'=>'早场 A轮', 'value'=>'1', 'info'=>'05:00-06:00'],
        '2'=> ['label'=>'早场 B轮', 'value'=>'2', 'info'=>'06:05-07:05'],
        '3'=> ['label'=>'上午场 A轮', 'value'=>'3', 'info'=>'08:20-09:20'],
        '4'=> ['label'=>'上午场 B轮', 'value'=>'4', 'info'=>'09:30-10:30'],
        '5'=> ['label'=>'下午场 A轮', 'value'=>'5', 'info'=>'12:50-13:50'],
        '6'=> ['label'=>'下午场 B轮', 'value'=>'6', 'info'=>'14:00-15:00'],
        '7'=> ['label'=>'晚场 A轮', 'value'=>'7', 'info'=>'16:50-18:50'],
        '8'=> ['label'=>'晚场 B轮', 'value'=>'8', 'info'=>'18:05-19:05'],
        '9'=> ['label'=>'凌晨场 A轮', 'value'=>'9', 'info'=>'21:00-22:00'],
        '10'=> ['label'=>'凌晨场 B轮', 'value'=>'10', 'info'=>'22:10-23:10'],
        '12'=> ['label'=>'视频拍摄', 'value'=>'12', 'info'=>'全时段'],
        '13'=> ['label'=>'线下送货', 'value'=>'13', 'info'=>'全时段'],
        '14'=> ['label'=>'福利品', 'value'=>'14', 'info'=>'全时段'],
        '15'=> ['label'=>'私域', 'value'=>'15', 'info'=>'全时段'],
        '16'=> ['label'=>'精准', 'value'=>'16', 'info'=>'全时段'],
      ],
      // 状态
      'status_name'=> ['0'=>'待确认', '1'=>'调拨中', '2'=>'完成'],
    ];
    return $data[$name];
  }

}