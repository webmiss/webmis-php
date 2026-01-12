<?php
namespace App\Model;

use Core\Model;

/* 调拨单 */
class ErpPurchaseAllocate extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_purchase_allocate');
  }

}
