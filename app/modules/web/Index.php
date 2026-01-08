<?php
namespace APP\Web;

use Core\Controller;
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
    // 传参
    View::assign('title', Env::$title);
    View::assign('copy', Env::$copy);
    // 视图
    View::render('home/index');
  }

}