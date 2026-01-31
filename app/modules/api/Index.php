<?php
namespace App\Api;

use Core\Controller;
use App\Model\User;

/* 接口 */
class Index extends Controller {

  /* 首页 */
  public function Index(): string {
    // 查询
    $m = new User();
    $m->Columns('id', 'uname');
    $data = $m->Find();
    self::Print($data, $m->GetSQL(), $m->GetNums());
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>'PHP Api']);
  }

}