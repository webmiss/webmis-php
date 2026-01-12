<?php
namespace App\Model;

use Core\Model;

/* 店铺 */
class ErpBaseShop extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_base_shop');
  }

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

}
