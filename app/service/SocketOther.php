<?php
namespace App\Service;

use App\Config\Env;
use App\Config\Socket as SocketCfg;
use App\Service\TokenAdmin;
use App\Service\TokenApi;
use App\Librarys\Aliyun\Bailian;
use App\Util\Util;

// use Data\Goods;
use App\Model\UserMsg;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/* Socket服务-其他 */
class SocketOther implements MessageComponentInterface {

  private $sever = 'other';  // 服务器
  private $lang = '';          // 语言
  private $clients = null;     // 连接
  private $uids = [];          // Uid
  private $cfg = [];           // 配置
  
  /* 构造函数 */
  function __construct() {
    $this->clients = new \SplObjectStorage;
    $this->cfg = SocketCfg::config($this->sever);
  }

  /* 消息 */
  function getMsg(int $fid, array $msg): void {
    // 发自己
    $this->send($fid, ['code'=>0, 'msg'=>$msg]);
    print_r($this->cfg);
  }

  /* 路由 */
  function router(string $uid, array $msg, object $from): void {
    if(Env::$mode_cli==='dev') print_r($msg);
    if(!isset($msg['type'])) {
      $from->send($this->GetJSON(['code'=>400, 'type'=>'类型错误']));
    }elseif($msg['type']=='msg') {
      // 消息
      $this->getMsg($uid, $msg);
    } else {
      // 心跳包
      $from->send($this->GetJSON(['code'=>0, 'type'=>'']));
    }
  }

  /* 群发 */
  function sendAll($data) {
    foreach($this->clients as $conn) {
      $conn->send(json_encode($data));
    }
    return;
  }

  /* 单发 */
  function send(int $uid, array $data) {
    if(!isset($uid) || !isset($this->uids['u'.$uid])) return;
    $id = $this->uids['u'.$uid];
    foreach($this->clients as $conn) {
      if($conn->resourceId==$id) return $conn->send($this->GetJSON($data));
    }
  }

  /* 返回JSON */
  function GetJSON(array $data=[]): string {
    // 语言
    $lang = $this->lang?:'en_US';
    if($lang && isset($data['code']) && !isset($data['msg'])) {
      $path = 'App\\Config\\Langs\\';
      $controller = $path.strtolower($lang);
      if(!class_exists($controller)) $controller = $path.'en_us';
      $class = new $controller();
      $method = 'code_'.$data['code'];
      $data['msg'] = method_exists($controller, $method)?$class::$$method:'';
    }
    return json_encode($data);
  }

  /* 连接 */
  function onOpen(ConnectionInterface $conn) {
    // 验证
    $uid = $this->verify((array)$conn->httpRequest->getUri());
    if($uid<0) return $conn->close();
    // 保存
    $this->clients->attach($conn);
    if($uid==0) {
      $this->uids['s'.$conn->resourceId] = $conn->resourceId;
    } else {
      $this->uids['u'.$uid] = $conn->resourceId;
    }
  }
  /* 消息 */
  function onMessage(ConnectionInterface $from, $msg) {
    // 数组
    $msg = json_decode($msg, true);
    if(!is_array($msg)) return $from->close();
    // 验证
    $uid = $this->verify((array)$from->httpRequest->getUri());
    if($uid===0 && isset($msg['fid'])) $uid = $msg['fid'];
    if($uid<0) return $from->close();
    // 路由
    $this->router($uid, $msg, $from);
  }
  /* 关闭 */
  function onClose(ConnectionInterface $conn) {
    $this->clients->detach($conn);
    if(isset($this->uids['s'.$conn->resourceId])) {
      // 系统
      unset($this->uids['s'.$conn->resourceId]);
      return;
    } else {
      // 用户
      foreach($this->uids as $key=>$val) {
        if($val==$conn->resourceId) {
          unset($this->uids[$key]);
          return;
        }
      }
    }
  }
  /* 错误 */
  function onError(ConnectionInterface $conn, \Exception $e) {
    return $conn->close();
  }

  /* 验证 */
  private function verify(array $param): int {
    // 参数
    $data = [];
    foreach($param as $val) $data[] = $val;
    $arr = isset($data[5])?Util::UrlToArray($data[5]):[];
    if(!$arr) return -1;
    $lang = isset($arr['lang'])?$arr['lang']:'en_US';
    $channel = isset($arr['channel'])?$arr['channel']:'';
    $token = isset($arr['token'])?$arr['token']:'';
    if(empty($channel) || empty($token)) return -1;
    $this->lang = $lang;
    // 验证
    if($token==Env::$key) {
      return 0;
    }elseif($channel=='admin') {
      $tData = TokenAdmin::Token($token);
      if(empty($tData)) return -1;
      return (string)$tData->uid;
    }elseif($channel=='api') {
      $tData = TokenApi::Token($token);
      if(empty($tData)) return -1;
      return (string)$tData->uid;
    }
    return -1;
  }

}