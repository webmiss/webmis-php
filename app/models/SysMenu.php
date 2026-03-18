<?php
namespace App\Model;

use Core\Model;

/* 系统菜单 */
class SysMenu extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn('default');
    $this->Table('sys_menus');
  }

}