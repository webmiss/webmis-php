<?php
namespace App\Model;

use Core\Model;

/* 用户消息 */
class UserMsg extends Model {

  /* 构造函数 */
  function __construct() {
    $this->DBConfig('default');
    $this->Table('user_msg');
  }

}