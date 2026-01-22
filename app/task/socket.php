<?php
namespace App\Task;


use Core\Base;
use App\Config\Env;
use App\Config\Socket as SocketCfg;
use Library\Redis;

use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;

use \WebSocket\Client;
use \WebSocket\Message\Text;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class SocketMsg implements MessageComponentInterface {
  public function onOpen(ConnectionInterface $conn) {}
  public function onMessage(ConnectionInterface $from, $msg) {}
  public function onClose(ConnectionInterface $conn) {}
  public function onError(ConnectionInterface $conn, \Exception $e) {}
}

/* Socket */
class socket extends Base {

  static private $config = [];         // 配置

  /* 服务器 */
  static function server(string $name='default') {
    // 配置
    self::$config = SocketCfg::config($name);
    // 启动
    error_reporting(E_ALL ^ E_DEPRECATED);
    $ws = new WsServer(new self::$config['service']());
    $server = IoServer::factory(new HttpServer($ws), self::$config['port'], self::$config['host']);
    $ws->enableKeepAlive($server->loop, self::$config['keep_alive']);
    $server->run();
  }

  /* 客户端 */
  static function client(string $channel='admin', string $msg='', string $cfgName='default'): bool {
    // 配置
    $config = SocketCfg::config($cfgName);
    try{
      // 发送
      $ws = new Client($config['type'].'://'.$config['host'].':'.$config['port'].'/?channel='.$channel.'&token='.Env::$key);
      $ws->send(new Text($msg));
      $ws->close();
      // 状态
      return $ws->isConnected();
    } catch (\Exception $e) {
      self::Print('[ SocketClient ]', $e->getMessage());
      return false;
    }
  }

}