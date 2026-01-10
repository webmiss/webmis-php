<?php
namespace App\Config;

/* Socket */
class Socket {

  /* 配置 */
  static function config(string $name='default'): array {
    $data = [
      'default'=> [
        'type'=> 'ws',                              // 类型
        'host'=> '127.0.0.1',                       // 主机
        'port'=> 9001,                              // 端口
        'keep_alive'=> 30,                          // 心跳(秒)
        'service'=> 'App\\Service\\SocketMsg',      // 接收类
        'info'=> [
          1=> ['title'=>'Ai助理', 'content'=>'Hi很高兴为您服务！', 'img'=>'https://php.webmis.vip/upload/robot.jpg'],
        ]
      ],
      'other'=> [
        'type'=> 'ws',                              // 类型
        'host'=> '127.0.0.1',                       // 主机
        'port'=> 9002,                              // 端口
        'keep_alive'=> 30,                          // 心跳(秒)
        'service'=> 'App\\Service\\SocketOther',    // 接收类
        'info'=> [
          1=> ['title'=>'Ai助理', 'content'=>'Hi很高兴为您服务！', 'img'=>'https://php.webmis.vip/upload/robot.jpg'],
        ]
      ]
    ];
    return $data[$name];
  }

}