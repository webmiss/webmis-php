<?php
namespace App\Model;

use Core\Model;

/* 分类 */
class ErpBaseCategory extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_base_category');
  }

  /* 列表 */
  static function GetList(array $where=[], array $columns=['id', 'name', 'status'], string $order_by='status DESC, sort DESC, name'): array {
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
