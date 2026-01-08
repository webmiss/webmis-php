<?php
namespace App\Model;

use Core\Model;

/* 用户表 */
class User extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConn();
    $this->Table('user');
  }

}