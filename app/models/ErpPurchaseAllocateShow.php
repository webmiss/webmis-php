<?php
namespace App\Model;

use Core\Model;

/* 调拨单-明细 */
class ErpPurchaseAllocateShow extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_purchase_allocate_show');
  }

}
