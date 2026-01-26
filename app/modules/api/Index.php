<?php
namespace App\Api;

use Core\Controller;

/* 接口 */
class Index extends Controller {

  /* 首页 */
  public function Index(): string {
    return self::GetJSON(['code'=>0, 'data'=>'PHP Api']);
  }

}