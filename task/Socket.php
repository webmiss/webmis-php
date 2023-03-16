<?php
namespace Task;

use Config\Socket as cfg;
use Service\Socket as SocketMsg;

use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;

/* Socket */
class Socket extends Base {

  /* 启动 */
  static function start(){
    $ws = new WsServer(new SocketMsg);
    $server = IoServer::factory(new HttpServer($ws), cfg::$port, cfg::$host);
    $ws->enableKeepAlive($server->loop,30);
    $server->run();
  }

}