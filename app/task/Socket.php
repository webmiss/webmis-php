<?php
namespace App\Task;


use Core\Base;
use App\Config\Env;
use App\Config\Socket as SocketCfg;
use App\Service\SocketMsg;
use Library\Redis;

use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;

use \WebSocket\Client;

/* Socket */
class Socket extends Base {

  static private $config = [];         // 配置

  /* 服务器 */
  static function server(string $name='default', ) {
    // 配置 self::$config['service']
    self::$config = SocketCfg::config($name);
    self::Print(self::$config);
    // $ws = new WsServer(new SocketMsg());
    // $server = IoServer::factory(new HttpServer($ws), self::$config['port'], self::$config['host']);
    // $ws->enableKeepAlive($server->loop, self::$config['keep_alive']);
    // $server->run();
  }

  // /* 客户端 */
  // static function client(string $channel) {
  //   $url = cfg::$type.'://'.cfg::$host.':'.cfg::$port.'/?channel='.$channel.'&token='.Env::$key;
  //   $ws = new Client($url);
  //   $ws->addMiddleware(new \WebSocket\Middleware\CloseHandler())->addMiddleware(new \WebSocket\Middleware\PingResponder())->onPing(function ($client, $conn, $message) {
  //     $redis = new Redis();
  //     $data = $redis->LRange(cfg::$redis_name, 0, cfg::$redis_leng);
  //     // if(!$data) sleep(cfg::$redis_time);
  //     foreach($data as $v) {
  //       $msg = $redis->LPop(cfg::$redis_name);
  //       if($msg) $client->text($msg);
  //     }
  //     $redis->Close();
  //   })->start();
  // }

}