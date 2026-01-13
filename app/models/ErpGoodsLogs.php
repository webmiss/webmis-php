<?php
namespace App\Model;

use Core\Model;

/* 商品日志 */
class ErpGoodsLogs extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('erp_goods_logs');
  }

}
