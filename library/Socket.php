<?php
namespace Library;

use \WebSocket\Client;

use Config\Env;
use Config\Socket as cfg;

/* Socket客户端 */
class Socket {

  /* 发送 */
  static function Send($channel, $data){
    $url = cfg::$type.'://'.cfg::$host.':'.cfg::$port.'/?channel='.$channel.'&token='.Env::$key;
    $ws = new Client($url);
    $ws->send(json_encode($data));
  }

}