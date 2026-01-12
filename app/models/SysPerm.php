<?php
namespace App\Model;

use Core\Model;

/* 权限 */
class SysPerm extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('sys_perm');
  }

}
