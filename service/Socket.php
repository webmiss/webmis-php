<?php
namespace Service;

use Config\Env;
use Config\Socket as cfg;
use Service\AdminToken;
use Service\ApiToken;
use Library\Aliyun\Bailian;
use Util\Util;

use Model\UserMsg;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/* Socket服务 */
class Socket implements MessageComponentInterface {

  public $lang = '';      // 语言
  public $clients = null; // 连接
  public $uids = [];      // Uid
  
  /* 构造函数 */
  function __construct() {
    $this->clients = new \SplObjectStorage;
  }

  /* 消息 */
  function getMsg(int $fid, array $msg): void {
    if(!isset($msg['gid']) || !isset($msg['type']) || !isset($msg['title']) || !isset($msg['content'])) {
      $this->send($fid, ['code'=>4000, 'type'=>'msg', 'title'=>'错误', 'content'=>'参数错误!']);
      return;
    }
    // 数据
    $time = time();
    $uid = isset($msg['uid'])?$msg['uid']:0;
    $data = ['id'=>0, 'time'=>date('Y-m-d H:i:s', $time)];
    $data['gid'] = $msg['gid'];
    $data['fid'] = $fid;
    $data['uid'] = $uid;
    $data['title'] = trim($msg['title']);
    $data['content'] = is_string($msg['content'])?trim($msg['content']):$msg['content'];
    $data['format'] = isset($msg['format'])?$msg['format']:0;
    $data['img'] = isset($msg['img'])?trim($msg['img']):'';
    $data['loading'] = isset($msg['loading'])?$msg['loading']:0;
    // 保存消息
    $m = new UserMsg();
    $m->Values([
      'gid'=> $data['gid'],
      'fid'=> $fid,
      'uid'=> $uid,
      'ctime'=> $time,
      'utime'=> $time,
      'format'=> $data['format'],
      'title'=> $data['title'],
      'content'=> is_array($data['content'])?json_encode($data['content']):$data['content'],
      'is_new'=> json_encode([$fid]),
      'pdate'=> date('Y-m-d', $time),
    ]);
    if($m->Insert()) {
      $data['id'] = $m->GetID();
    } else {
      $data['code'] = 5000;
      $this->send($fid, $data);
    }
    // 智能机器人
    $data['code'] = 0;
    $data['type'] = 'msg';
    if($data['gid']==1) {
      // 发自己
      $this->send($fid, $data);
      if($data['format']===0) {
        // 阿里云百炼
        $res = Bailian::GetMsg([['role'=>'user', 'content'=>$data['content']]]);
        $data['id'] = 0;
        $data['fid'] = 0;
        $data['uid'] = $fid;
        $data['title'] = cfg::$service[$data['gid']]['title'];
        $data['content'] = $res;
        $data['img'] = cfg::$service[$data['gid']]['img'];
        $data['loading'] += 1;
        $this->send($fid, $data);
      }
    } elseif($uid && $fid) {
      // 发对方
      $this->send($uid, $data);
      // 发自己
      $this->send($fid, $data);
    }
  }

  /* 路由 */
  function router(string $uid, $msg, $from): void {
    if(!isset($msg['type'])){
      $from->send($this->GetJSON(['code'=>400, 'type'=>'类型错误']));
    }elseif($msg['type']=='msg'){
      // 消息
      $this->getMsg($uid, $msg);
    }elseif($msg['type']=='online'){
      // 是否在线
      $list = [];
      $uids = array_keys($this->uids);
      foreach ($msg['ids'] as $id) {
        $list[(string)$id] = in_array($id, $uids);
      }
      $from->send($this->GetJSON(['code'=>0, 'type'=>'online', 'data'=>['total'=>count($uids), 'list'=>$list]]));
    }else{
      // 心跳包
      $from->send($this->GetJSON(['code'=>0, 'type'=>'']));
    }
  }

  /* 群发 */
  function sendAll($data) {
    foreach ($this->clients as $conn) {
      $conn->send(json_encode($data));
    }
    return;
  }

  /* 单发 */
  function send(int $uid, array $data) {
    if(!isset($uid) || !isset($this->uids[$uid])) return;
    $id = $this->uids[$uid];
    foreach ($this->clients as $conn) {
      if($conn->resourceId==$id) return $conn->send($this->GetJSON($data));
    }
  }

  /* 返回JSON */
  function GetJSON(array $data=[]): string {
    // 语言
    $lang = $this->lang?:'en_US';
    if($lang && isset($data['code']) && !isset($data['msg'])) {
      $name = 'Config\\Langs\\'.$lang;
      $class = new $name();
      $action = 'code_'.$data['code'];
      $data['msg'] = $class::$$action;
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
    $this->uids[$uid] = $conn->resourceId;
  }
  /* 消息 */
  function onMessage(ConnectionInterface $from, $msg) {
    // 内容
    $msg = json_decode($msg, true);
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
  private function verify($url): int {
    // 参数
    $param = $url;
    $data = [];
    foreach($param as $val) $data[] = $val;
    $arr = Util::UrlToArray($data[5]);
    if(empty($arr)) return '';
    $lang = isset($arr['lang'])?$arr['lang']:'en_US';
    $channel = isset($arr['channel'])?$arr['channel']:'';
    $token = isset($arr['token'])?$arr['token']:'';
    if(empty($channel) || empty($token)) return '';
    $this->lang = $lang;
    // 验证
    if($token==Env::$key){
      return 0;
    }elseif($channel=='admin'){
      $tData = AdminToken::Token($token);
      if(empty($tData)) return -1;
      return (string)$tData->uid;
    }elseif($channel=='api'){
      $tData = ApiToken::Token($token);
      if(empty($tData)) return -1;
      return (string)$tData->uid;
    }
    return -1;
  }

}