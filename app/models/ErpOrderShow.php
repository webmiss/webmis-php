<?php
namespace App\Model;

use Core\Model;

/* 订单-明细 */
class ErpOrderShow extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_order_show');
  }

}
