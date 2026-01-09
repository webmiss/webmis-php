<?php
namespace App\Model;

use Core\Model;

/* 系统菜单表 */
class SysMenu extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('sys_menus');
  }

}