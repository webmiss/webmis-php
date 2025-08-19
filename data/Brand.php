<?php
namespace Data;

use Model\ErpBaseBrand;

/* 品牌 */
class Brand {

  /* 列表 */
  static function GetList(array $where=[], array $columns=['value', 'rule'], string $order_by='sort DESC, id ASC'): array {
    $m = new ErpBaseBrand();
    $m->Columns('name', ...$columns);
    $m->Where(implode(' AND ', $where));
    $m->Order($order_by);
    $all = $m->Find();
    $data = [];
    foreach($all as $v){
      $data[$v['name']]=$v;
    }
    return $data;
  }

}