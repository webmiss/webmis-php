<?php
namespace App\Model;

use Core\Model;

/* 组织架构 */
class ErpBaseOrganization extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_base_organization');
  }

  /* 列表 */
  static function GetList(array $where = [], array $columns = ['name'], string $order_by = 'sort DESC, id ASC'): array {
    $m = new ErpBaseOrganization();
    $m->Columns('id', ...$columns);
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
