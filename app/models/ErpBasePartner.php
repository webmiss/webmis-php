<?php
namespace App\Model;

use Core\Model;

/* 分仓 */
class ErpBasePartner extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_base_partner');
  }

  /* 列表 */
  static function GetList(array $where = [], array $columns = ['name', 'status'], string $order_by = 'status DESC, sort DESC, name ASC'): array {
    $m = new ErpBasePartner();
    $m->Columns('wms_co_id', ...$columns);
    $m->Where(implode(' AND ', $where));
    $m->Order($order_by);
    $all = $m->Find();
    $data = [];
    foreach($all as $v){
      $data[$v['wms_co_id']] = $v;
    }
    return $data;
  }

}