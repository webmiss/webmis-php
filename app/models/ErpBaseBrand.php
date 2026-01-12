<?php
namespace App\Model;

use Core\Model;

/* 品牌 */
class ErpBaseBrand extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_base_brand');
  }

  /* 列表 */
  static function GetList(array $where=[], array $columns=['value', 'rule', 'status'], string $order_by='status DESC, sort DESC, id ASC'): array {
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
