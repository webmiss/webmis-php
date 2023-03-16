<?php
namespace Service;

use Config\Env;
use Service\ApiToken;
use Service\AdminToken;
use Util\Util;

use Ratchet\WebSocket\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/* Socket服务 */
class Socket implements MessageComponentInterface {

  public $clients=null; //连接
  public $uids=[];      //Uid

  /* 构造函数 */
  function __construct() {
    $this->clients = new \SplObjectStorage;
  }

  /* 消息 */
  function getMsg(string $uid, array $msg) {
    // 群发
    if($uid=='0' && !isset($msg['to'])) return $this->sendAll($msg);
    // 单发
    return $this->send($msg);
  }

  /* 路由 */
  function router(string $uid, $msg, $from): void {
    $data = json_decode($msg, true);
    if($data['type']=='msg'){
      $this->getMsg($uid, $data);
    }else{
      $from->send(json_encode(['code'=>0, 'type'=>'', 'msg'=>'成功']));
    }
  }

  /* 群发 */
  function sendAll($data) {
    foreach ($this->clients as $conn) {
      $conn->send(json_encode($data));
    }
  }

  /* 单发 */
  function send(array $data) {
    if(!isset($data['to']) || !isset($this->uids[$data['to']])) return;
    $id = $this->uids[$data['to']];
    foreach ($this->clients as $conn) {
      if($conn->resourceId==$id) $conn->send(json_encode($data));
    }
  }

  /* 连接 */
  function onOpen(ConnectionInterface $conn) {
    // 验证
    $uid = $this->verify((array)$conn->httpRequest->getUri());
    if($uid=='') return $conn->close();
    // 保存
    $this->clients->attach($conn);
    $this->uids[$uid] = $conn->resourceId;
  }
  /* 消息 */
  function onMessage(ConnectionInterface $from, $msg) {
    // 验证
    $uid = $this->verify((array)$from->httpRequest->getUri());
    if($uid=='') return $from->close();
    // 路由
    $this->router($uid, $msg, $from);
  }
  /* 关闭 */
  function onClose(ConnectionInterface $conn) {
    $this->clients->detach($conn);
    foreach($this->uids as $key=>$val) {
      if($val==$conn->resourceId){
        unset($this->uids[$key]);
        break;
      }
    }
  }
  /* 错误 */
  function onError(ConnectionInterface $conn, \Exception $e) {
    return $conn->close();
  }

  /* 验证 */
  private function verify($url): string {
    // 参数
    $param = $url;
    $data = [];
    foreach($param as $val) $data[] = $val;
    $arr = Util::UrlToArray($data[5]);
    if(empty($arr)) return '';
    $channel = isset($arr['channel'])?$arr['channel']:'';
    $token = isset($arr['token'])?$arr['token']:'';
    if(empty($channel) || empty($token)) return '';
    // 验证
    if($token==Env::$key){
      return '0';
    }elseif($channel=='api'){
      $tData = ApiToken::Token($token);
      if(empty($tData)) return '';
      return (string)$tData->uid;
    }elseif($channel=='admin'){
      $tData = AdminToken::Token($token);
      if(empty($tData)) return '';
      return (string)$tData->uid;
    }
    return '';
  }

}