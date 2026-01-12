<?php
namespace App\Model;

use Core\Model;

/* 库存明细 */
class ErpPurchaseStock extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_purchase_stock');
  }

}

