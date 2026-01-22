<?php
namespace App\Home;

use Core\Controller;
use Core\View;
use App\Config\Env;

/* 网站 */
class index extends Controller {

  /* 首页 */
  public function index(): void {
    // 传参
    View::assign('title', Env::$title);
    View::assign('copy', Env::$copy);
    // 视图
    View::render('home/index');
  }

}