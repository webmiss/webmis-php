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
    // 传参
    View::assign('title', Env::$title);
    View::assign('copy', Env::$copy);
    // 视图
    View::render('home/index');
  }

}