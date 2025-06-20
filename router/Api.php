<?php
namespace Router;

use Illuminate\Container\Container;
use Middleware\Cors;

class Api {

  static function Init() {
    // 允许跨域请求
    Cors::Init();
    // 路由
    $app = Container::getInstance();
    $app['router']->group(['namespace'=>'App\Api', 'prefix'=>'api'], function ($router) {
      // 首页
      $router->get('', "Index@Index");
      $router->post('index/html', "Index@GetHtml");
      $router->post('index/version', "Index@Version");
      // 登录
      $router->post('user/login', "User@Login");
      $router->post('user/token', "User@Token");
      $router->get('user/vcode/{uname}', "User@Vcode");
      $router->post('user/get_vcode', "User@GetVcode");
      $router->post('user/change_passwd', "User@ChangePasswd");
      $router->post('user/change_uinfo', "User@ChangeUinfo");
      $router->post('user/upimg', "User@Upimg");
      // 消息
      $router->post('msg/list', "Msg@List");
      $router->post('msg/sea', "Msg@Sea");
      $router->post('msg/read', "Msg@Read");
      $router->post('msg/oss_sgin', "Msg@OssSgin");
    });
    
  }

}