<?php
namespace App\Model;

use Core\Model;

/* 角色 */
class SysRole extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('sys_role');
  }

}
