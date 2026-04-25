<?php
namespace App\Model;

use Core\Model;

/* 用户信息 */
class UserInfo extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConfig('default');
    $this->Table('user_info');
  }

}
