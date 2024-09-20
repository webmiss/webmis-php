<?php
namespace App\Admin;

use Service\Base;

/* 控制台 */
class Index extends Base {

  /* 首页 */
  static function Index() {
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'PHP Admin']);
  }

}
