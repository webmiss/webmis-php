<?php
namespace App\Model;

use Core\Model;

/* 供应商 */
class ErpBaseSupplier extends Model {

  /* 构造函数 */
  function __construct(){
    $this->DBConn();
    $this->Table('erp_base_supplier');
  }

}
