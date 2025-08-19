<?php
namespace Data;

use Model\ErpBasePartner;

class Partner {

  /* 列表 */
  static function GetList(array $where = [], array $columns = ['name'], string $order_by = 'sort DESC, id ASC'): array {
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
