<?php
namespace APP\Api;

use Core\Controller;

/* 接口 */
class Index extends Controller {

  /* 首页 */
  public function Index(string $uid=''): string {
    // 参数
    $json = self::Json();
    $lang = self::JsonName($json, 'lang');
    self::Print('data', $lang, $uid);
    return 'Api';
  }

  /* 测试 */
  public function Test(): string {
    return 'Test';
  }

}