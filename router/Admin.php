<?php
namespace Router;

use Illuminate\Container\Container;
use Middleware\Cors;

class Admin {

  static function Init(){
    // 允许跨域请求
    Cors::Init();
    // 路由
    $app = Container::getInstance();
    $app['router']->group(['namespace'=>'App\Admin', 'prefix' => 'admin'], function($router){
      // 首页
      $router->get('', "Index@Index");
      $router->get('index/getConfig', "Index@GetConfig");
      $router->post('index/getChart', "Index@GetChart");
      // 登录
      $router->post('user/login', "User@Login");
      $router->post('user/token', "User@Token");
      $router->get('user/vcode/{uname}', "User@Vcode");
      $router->post('user/get_vcode', "User@GetVcode");
      $router->post('user/change_passwd', "User@ChangePasswd");
      $router->post('user/change_uinfo', "User@ChangeUinfo");
      $router->post('user/upimg', "User@Upimg");
      // 消息
      $router->get('msg/socket', "Msg@Socket");
      $router->post('msg/list', "Msg@List");
      $router->post('msg/sea', "Msg@Sea");
      $router->post('msg/read', "Msg@Read");
      $router->post('msg/del', "Msg@Del");
      $router->post('msg/oss_sgin', "Msg@OssSgin");
      // 文件管理
      $router->post('sys_file/list', "SysFile@List");
      $router->post('sys_file/mkdir', "SysFile@Mkdir");
      $router->post('sys_file/rename', "SysFile@Rename");
      $router->post('sys_file/upload', "SysFile@Upload");
      $router->post('sys_file/down', "SysFile@Down");
      $router->post('sys_file/remove', "SysFile@Remove");
      // 系统用户
      $router->post('sys_user/total', "SysUser@Total");
      $router->post('sys_user/list', "SysUser@List");
      $router->post('sys_user/save', "SysUser@Save");
      $router->post('sys_user/del', "SysUser@Del");
      $router->post('sys_user/export', "SysUser@Export");
      $router->post('sys_user/get_select', "SysUser@GetSelect");
      $router->post('sys_user/get_perm', "SysUser@GetPerm");
      // 系统菜单
      $router->post('sys_menus/total', "SysMenus@Total");
      $router->post('sys_menus/list', "SysMenus@List");
      $router->post('sys_menus/save', "SysMenus@Save");
      $router->post('sys_menus/del', "SysMenus@Del");
      $router->post('sys_menus/export', "SysMenus@Export");
      $router->post('sys_menus/get_menus_all', "SysMenus@GetMenusAll");
      $router->post('sys_menus/get_menus_perm', "SysMenus@GetMenusPerm");
      // 系统角色
      $router->post('sys_role/total', "SysRole@Total");
      $router->post('sys_role/list', "SysRole@List");
      $router->post('sys_role/save', "SysRole@Save");
      $router->post('sys_role/del', "SysRole@Del");
      $router->post('sys_role/export', "SysRole@Export");
      $router->post('sys_role/get_perm', "SysRole@GetPerm");
      // 静态页面
      $router->post('web_html/total', "WebHtml@Total");
      $router->post('web_html/list', "WebHtml@List");
      $router->post('web_html/save', "WebHtml@Save");
      $router->post('web_html/del', "WebHtml@Del");
      $router->post('web_html/export', "WebHtml@Export");
    });
    
  }

}