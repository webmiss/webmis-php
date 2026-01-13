<?php
namespace App\Model;

use Core\Model;

/* 其它出入库 */
class ErpOrderInout extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_order_inout');
  }

}
