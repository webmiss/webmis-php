<?php
namespace App\Config;

/* 公共配置 */
class Env {
  static $mode = '';                                     // 开发环境: dev
  static $mode_cli = 'dev';                              // 开发环境: dev
  static $title = 'WebMIS 3.0';                          // 项目名称
  static $copy = 'webmis.vip &copy; 2026';               // 版权
  static $key = 'e4b99adec618e653400966be536c45f8';      // KEY
  /* Token */
  static $admin_token_prefix = 'WebmisAdmin';               // 前缀
  static $admin_token_time = 2*3600;                        // 有效时长(2小时)
  static $admin_token_auto = true;                          // 自动续期
  static $admin_token_sso = false;                          // 单点登录
  static $api_token_prefix = 'WebmisApi';                   // 前缀
  static $api_token_time = 7*24*3600;                       // 有效时长(7天)
  static $api_token_auto = true;                            // 自动续期
  static $api_token_sso = false;                            // 单点登录
}