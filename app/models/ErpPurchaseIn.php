<?php
namespace App\Model;

use Core\Model;

/* 采购入库 */
class ErpPurchaseIn extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_purchase_in');
  }

}
