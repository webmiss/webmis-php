<?php
namespace App\Model;

use Core\Model;

/* 采购退货 */
class ErpPurchaseOut extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_purchase_out');
  }

}
