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
      // 消息
      $router->get('msg/socket', "Msg@Socket");
      $router->post('msg/list', "Msg@List");
      $router->post('msg/sea', "Msg@Sea");
      $router->post('msg/read', "Msg@Read");
      // 个人资料
      $router->post('user_info/list', "UserInfo@List");
      $router->post('user_info/edit', "UserInfo@Edit");
      $router->post('user_info/upimg', "UserInfo@Upimg");
      // 修改密码
      $router->post('user_passwd/edit', "UserPasswd@Edit");
      // 文件管理
      $router->post('sys_file/list', "SysFile@List");
      $router->post('sys_file/mkdir', "SysFile@Mkdir");
      $router->post('sys_file/rename', "SysFile@Rename");
      $router->post('sys_file/upload', "SysFile@Upload");
      $router->post('sys_file/down', "SysFile@Down");
      $router->post('sys_file/remove', "SysFile@Remove");
      // 用户管理
      $router->post('sys_user/list', "SysUser@List");
      $router->post('sys_user/add', "SysUser@Add");
      $router->post('sys_user/edit', "SysUser@Edit");
      $router->post('sys_user/del', "SysUser@Del");
      $router->post('sys_user/state', "SysUser@State");
      $router->post('sys_user/perm', "SysUser@Perm");
      $router->post('sys_user/info', "SysUser@Info");
      $router->post('sys_user/export', "SysUser@Export");
      // 系统菜单
      $router->post('sys_menus/list', "SysMenus@List");
      $router->post('sys_menus/add', "SysMenus@Add");
      $router->post('sys_menus/edit', "SysMenus@Edit");
      $router->post('sys_menus/del', "SysMenus@Del");
      $router->post('sys_menus/perm', "SysMenus@Perm");
      $router->post('sys_menus/export', "SysMenus@Export");
      $router->post('sys_menus/getMenusAll', "SysMenus@GetMenusAll");
      $router->post('sys_menus/getMenusPerm', "SysMenus@GetMenusPerm");
      // 系统角色
      $router->post('sys_role/list', "SysRole@List");
      $router->post('sys_role/add', "SysRole@Add");
      $router->post('sys_role/edit', "SysRole@Edit");
      $router->post('sys_role/del', "SysRole@Del");
      $router->post('sys_role/perm', "SysRole@Perm");
      $router->post('sys_role/export', "SysRole@Export");
      $router->post('sys_role/permList', "SysRole@PermList");
      $router->post('sys_role/roleList', "SysRole@RoleList");
      // 新闻
      $router->post('news/list', "WebNews@List");
      $router->post('news/add', "WebNews@Add");
      $router->post('news/edit', "WebNews@Edit");
      $router->post('news/del', "WebNews@Del");
      $router->post('news/state', "WebNews@State");
      $router->post('news/get_class', "WebNews@GetClass");
      $router->post('news/get_content', "WebNews@GetContent");
      $router->post('news/content', "WebNews@Content");
      $router->post('news/up_img', "WebNews@UpImg");
      // 新闻分类
      $router->post('news_class/list', "WebNewsClass@List");
      $router->post('news_class/add', "WebNewsClass@Add");
      $router->post('news_class/edit', "WebNewsClass@Edit");
      $router->post('news_class/del', "WebNewsClass@Del");
      $router->post('news_class/state', "WebNewsClass@State");
    });
    
  }

}