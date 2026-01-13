<?php
namespace App\Model;

use Core\Model;

/* 采购退货-明细 */
class ErpPurchaseOutShow extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_purchase_out_show');
  }

}
