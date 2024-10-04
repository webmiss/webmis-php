<?php
namespace Config\Langs;

/* 简体中文 */
class zh_CN {

  /* Code */
  static string $code_0 = '成功';
  static string $code_4000 = '参数错误';
  static string $code_4001 = 'Token验证失败';
  static string $code_4010 = '暂无数据';
  static string $code_5000 = '服务器错误';
  /* Public */
  static string $enable = '正常';
  static string $disable = '禁用';
  static string $export_limit = '总数不能大于%s';
  /* Login */
  static string $login_uname = '请输入用户名/手机/邮箱';
  static string $login_passwd = '请输入%s~%s位密码';
  static string $login_vcode = '请输入验证码';
  static string $login_verify = '帐号或密码错误';
  static string $login_verify_vcode = '验证码错误';
  static string $login_verify_vcode_time = '请%s秒后重试';
  static string $login_verify_vcode_max = '超过当天最大上限%s次';
  static string $login_verify_status = '该用户已被禁用';
  static string $login_verify_perm = '该用户不允许登录';
  /* SysUser */
  static string $sys_user_uname = '请输入用户名/手机/邮箱';
  static string $sys_user_passwd = '密码为英文字母开头%s～$s位';
  static string $sys_user_is_exist = '该用户已存在';
  /* SysRole */
  static string $sys_role_name = '角色%s～%s位字符';
  /* WebHtml */
  static string $web_html_title = '标题$s～%s位字符';
  static string $web_html_name = '名称$s～%s位字符';

}