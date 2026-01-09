<?php
namespace APP\Web;

use Core\Controller;
use Core\Redis;
use Core\View;
use App\Config\Env;
use App\Model\User;

/* 网站 */
class Index extends Controller {

  /* 首页 */
  public function Index(): void {
    // 模型
    $m = new User();
    $m->Columns('id', 'uname');
    $data = $m->Find();
    self::Print($data);
    // Redis
    $redis = new Redis();
    $res1 = $redis->Set('test', 'Test');
    $val = $redis->Gets('test');
    $res2 = $redis->Del('test');
    self::Print($res1, $res2, $val);
    // 传参
    View::assign('title', Env::$title);
    View::assign('copy', Env::$copy);
    // 视图
    View::render('home/index');
  }

}