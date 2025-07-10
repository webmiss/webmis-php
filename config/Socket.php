<?php
namespace Config;

/* Socket配置 */
class Socket {

  static $type = 'ws';                    // 类型
  static $host = '127.0.0.1';             // 主机
  static $port = 9003;                    // 端口
  static $redis_name = 'msg_contents';    // Redis名称
  static $redis_time = 3;                 // 读取间隔
  static $redis_leng = 1000;              // 读取条数
  // 消息组
  static $service = [
    1=> ['title'=>'Ai助理', 'content'=>'Hi很高兴为您服务！', 'img'=>'https://php.webmis.vip/upload/robot.jpg'],
  ];

}