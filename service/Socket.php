<?php
namespace Service;

use Config\Env;
use Config\Socket as cfg;
use Service\AdminToken;
use Service\ApiToken;
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
  function getMsg(string $uid, array $msg) {
    // 验证
    if(!isset($msg['uid'])) return;
    $gid = isset($msg['gid'])?$msg['gid']:'';
    $fid = isset($msg['fid'])?$msg['fid']:'';
    $title = isset($msg['title'])?trim($msg['title']):'';
    $content = isset($msg['content'])?trim($msg['content']):'';
    if($gid=='' || $title=='' || $content=='') {
      $msg['code'] = 4000;
      $msg['uid'] = $uid;
      return $this->send($msg);
    }
    // 群发
    if($msg['uid']=='0') return $this->sendAll($msg);
    // 时间
    $time = time();
    if(!isset($msg['time'])) $msg['time']=date('Y-m-d H:i:s', $time);
    // 保存
    if($gid==0 || $fid==0) {
      $m = new UserMsg();
      $m->Values([
        'gid'=> $gid,
        'format'=> isset($msg['format'])?$msg['format']:0,
        'uid'=> $msg['uid'],
        'fid'=> isset($msg['fid'])?$msg['fid']:$uid,
        'ctime'=> $time,
        'utime'=> $time,
        'is_new'=> $uid?json_encode([$uid]):'',
        'content'=> $content,
      ]);
      if($m->Insert()){
        $msg['code'] = 0;
        $msg['id'] = $m->GetID();
        $msg['uid'] = $msg['uid'];
        $msg['fid'] = $uid;
      }else{
        $msg['code'] = 5000;
        $msg['uid'] = $uid;
      }
    }
    // 消息
    if($gid==0 || $fid==0) {
      return $this->send($msg);
    } elseif($gid==1 && $fid!=0) {
      $res = file_get_contents(cfg::$chatbot.urlencode(trim($msg['content'])));
      $data = json_decode($res);
      // 自动回复
      $msg['fid'] = 0;
      $msg['title'] = cfg::$name[1];
      $msg['content'] = $data->data->info->text;
      return $this->send($msg);
    }
  }

  /* 路由 */
  function router(string $uid, $msg, $from): void {
    $data = json_decode($msg, true);
    if($data['type']=='msg'){
      // 消息
      $this->getMsg($uid, $data);
    }elseif($data['type']=='online'){
      // 是否在线
      $list = [];
      $uids = array_keys($this->uids);
      foreach ($data['ids'] as $id) {
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
  function send(array $data) {
    if(!isset($data['uid']) || !isset($this->uids[$data['uid']])) return;
    $id = $this->uids[$data['uid']];
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
    $lang = isset($arr['lang'])?$arr['lang']:'en_US';
    $channel = isset($arr['channel'])?$arr['channel']:'';
    $token = isset($arr['token'])?$arr['token']:'';
    if(empty($channel) || empty($token)) return '';
    $this->lang = $lang;
    // 验证
    if($token==Env::$key){
      return '0';
    }elseif($channel=='admin'){
      $tData = AdminToken::Token($token);
      if(empty($tData)) return '';
      return (string)$tData->uid;
    }elseif($channel=='api'){
      $tData = ApiToken::Token($token);
      if(empty($tData)) return '';
      return (string)$tData->uid;
    }
    return '';
  }

}