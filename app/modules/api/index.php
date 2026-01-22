<?php
namespace App\Api;

use Core\Controller;

/* 接口 */
class index extends Controller {

  /* 首页 */
  public function index(string $uid=''): string {
    // 参数
    $json = self::Json();
    $lang = self::JsonName($json, 'lang');
    self::Print('data', $lang, $uid);
    return self::GetJSON(['code'=>0, 'data'=>'PHP Api']);
  }

}