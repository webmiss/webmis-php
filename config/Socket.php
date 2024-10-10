<?php
namespace Config;

/* Socket配置 */
class Socket {

  static $type = 'ws';            //类型
  static $host = '127.0.0.1';     //主机
  static $port = 9001;            //端口
  static $name = [1=> '小助理'];
  static $chatbot = 'http://ajax-api.itheima.net/api/robot?spoken=';

}