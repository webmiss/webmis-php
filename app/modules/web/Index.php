<?php
namespace App\Web;

use Core\Controller;
use Core\View;
use App\Config\Env;

/* 网站 */
class Index extends Controller {

  /* 首页 */
  public function index(): string {
    // 传参
    View::assign('title', Env::$title);
    View::assign('copy', Env::$copy);
    // 视图
    return View::render('web/index');
  }

}