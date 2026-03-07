<?php
namespace App\Service;

class Status {

  /* 店铺 */
  static function Shop(string $name): array {
    $data = [
      'city_name'=> ['0'=>'其它', '1'=>'瑞丽', '2'=>'平洲'],
      'class_name'=> ['0'=>'其它', '1'=>'线下', '2'=>'淘宝', '3'=>'抖音', '4'=>'视频号', '5'=>'视频小店', '6'=>'拼多多', '7'=>'快手'],
      'status_name'=> ['0'=>'禁用', '1'=>'正常'],
    ];
    return $data[$name];
  }

  /* 主仓&分仓 */
  static function Partner(string $name): array {
    $data = [
      'type_name'=> ['0'=>'主仓', '1'=>'分仓'],
      'class_name'=> [
        '0'=> '其它',
        '1'=> '库房',
        '2'=> '瑞丽',
        '3'=> '平洲',
        '4'=> '四会',
        '5'=> '直播间',
        '6'=> '客服仓',
        '7'=> '私域仓',
        '8'=> '送货仓',
        '9'=> '福利仓',
        '10'=> '视频仓',
        '11'=> '物资仓',
        '12'=> '借货',
      ],
      'status_name'=> ['0'=>'禁用', '1'=>'正常'],
    ];
    return $data[$name];
  }

  /* 品牌 */
  static function Brand(string $name): array {
    $data = [
      'class_name'=> [
        '0'=> '其它',
        '1'=> '公司',
        '2'=> '瑞丽',
        '3'=> '平洲',
        '4'=> '四会',
        '5'=> '曼德勒'
      ],
      'status_name'=> ['0'=>'禁用', '1'=>'正常'],
    ];
    return $data[$name];
  }

  /* 分类 */
  static function Category(string $name): array {
    $data = [
      'status_name'=> ['0'=>'禁用', '1'=>'正常'],
    ];
    return $data[$name];
  }

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

  /* 采购退货 */
  static function PurchaseOut(string $name): array {
    $data = [
      'type_name'=> ['0'=>'普通退货', '1'=>'当天下架', '2'=>'客服退货'],
      'status_name'=> ['0'=>'待确认', '1'=>'待退货', '2'=>'完成'],
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

  /* 店铺订单 */
  static function ShopOrder(string $name): array {
    $data = [
      // 平台类型
      'type_name'=> ['XX'=> '线下', 'TB'=> '淘宝', 'DY'=>'抖音', 'WX'=>'微信', 'WD'=>'微店', 'PDD'=>'拼多多'],
      // 订单状态
      'status_name'=> [
        'UNKNOWN'=> '未知',
        'DELETED'=> '已删除',
        'CLOSE'=> '已关闭',
        'CANCEL'=> '已取消',
        'UNPAID'=> '未付款',
        'PART_PAID'=> '部分付款',
        'NOT_SHIPPED'=> '未发货',
        'PART_SHIPPED'=> '部分发货',
        'SHIPPED'=> '已发货',
        'REJECTED'=> '已拒收',
        'COMPLETE'=> '已完成',
        'BILL_SHIPPED'=> '已寄票',
        'BILL_COMPLETE'=> '已收票',
        'PAUSE'=> '暂停',
        'LOCKED'=> '锁定',
        'O2O_ORDER_RECEIVED'=> '已接单',
        'O2O_ORDER_PICKED'=> '已拣货',
        'O2O_ORDER_PICKED'=> '已拣货',
      ],
      // 售后状态
      'refund_name'=> [
        'NONE'=> '未知',
        'NO_REFUND'=> '无退款',
        'PART_REFUNDED'=> '部分退款',
        'ALL_REFUNDED'=> '全部退款',
      ],
      // 旗帜名称
      'flag_name'=> [
        'NONE'=> '无',
        'RED'=> '红',
        'YELLOW'=> '黄',
        'GREEN'=> '绿',
        'BLUE'=> '蓝',
        'PURPLE'=> '紫',
        'ORANGE'=> '橙',
        'GREY'=> '灰',
        'LIGHT_BLUE'=> '浅蓝',
        'LIGHT_PINK'=> '浅粉',
        'DARK_GREEN'=> '深绿',
        'PEACH_RED'=> '桃红',
      ],
    ];
    return $data[$name];
  }

  /* 其它出入库 */
  static function OrderInout(string $name): array {
    $data = [
      'type_name'=> ['0'=>'销售', '1'=>'退货'],
      'status_name'=> ['0'=>'待确认', '1'=>'已确认'],
    ];
    return $data[$name];
  }

}