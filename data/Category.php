<?php
namespace Data;

use Model\ErpBaseCategory;

/* 分类 */
class Category {

  /* 列表 */
  static function GetList(array $where=['state=1'], array $columns=['id', 'name'], string $order_by='sort, name'): array {
    $m = new ErpBaseCategory();
    $m->Columns('name', ...$columns);
    $m->Where(implode(' AND ', $where));
    $m->Order($order_by);
    $all = $m->Find();
    $data = [];
    foreach($all as $v){
      $data[$v['id']] = $v;
    }
    return $data;
  }

}