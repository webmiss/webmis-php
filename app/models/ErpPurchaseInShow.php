<?php
namespace App\Model;

use Core\Model;

/* 采购入库-明细 */
class ErpPurchaseInShow extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_purchase_in_show');
  }

}
