<?php
namespace Service;

use Config\Env;
use Config\Socket as cfg;
use Service\AdminToken;
use Service\ApiToken;
use Library\Baidu\Builder;
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
    $fid = isset($msg['fid'])?$msg['fid']:$uid;
    $title = isset($msg['data']['title'])?trim($msg['data']['title']):'';
    $content = isset($msg['data']['content'])?trim($msg['data']['content']):'';
    $format = isset($msg['data']['format'])?$msg['data']['format']:0;
    $img = isset($msg['data']['img'])?trim($msg['data']['img']):'';
    if($gid=='' || $title=='' || $content=='') {
      return $this->send($uid, ['code'=>4000, 'type'=>$msg['type'], 'data'=>$msg['data']]);
    }
    // 群发
    // if($msg['uid']=='0') return $this->sendAll($msg);
    // 数据
    $time = time();
    $data = ['time'=>date('Y-m-d H:i:s'), 'data'=>[]];
    $data['gid'] = $gid;
    $data['fid'] = $fid;
    $data['type'] = $msg['type'];
    $data['data']['gid'] = $gid;
    $data['data']['fid'] = $fid;
    $data['data']['uid'] = $msg['uid'];
    $data['data']['time'] = $data['time'];
    $data['data']['title'] = $title;
    $data['data']['format'] = $format;
    $data['data']['content'] = $content;
    $data['data']['img'] = $img;
    // 保存
    if($gid==0 || $fid==0) {
      $m = new UserMsg();
      $m->Values([
        'gid'=> $gid,
        'format'=> $format,
        'uid'=> $msg['uid'],
        'fid'=> $fid,
        'ctime'=> $time,
        'utime'=> $time,
        'is_new'=> $uid?json_encode([$uid]):'',
        'content'=> $content,
      ]);
      if($m->Insert()){
        $data['code'] = 0;
        $data['data']['id'] = $m->GetID();
      }else{
        return $this->send($uid, ['code'=>5000, 'type'=>$msg['type'], 'data'=>$msg['data']]);
      }
    }
    // 消息
    if($gid==0) {
      // 发对方
      $this->send($msg['uid'], $data);
      // 发自己
      $data['fid'] = $msg['uid'];
      $this->send($uid, $data);
    } elseif($gid==1) {
      // 百度Ai
      $res = Builder::GetAnswer(['query'=> $content]);
      // 自动回复
      $data['code'] = 0;
      $data['fid'] = 0;
      $data['data']['id'] = 0;
      $data['data']['fid'] = 0;
      $data['data']['uid'] = 0;
      $data['data']['img'] = cfg::$service[1]['img'];
      $data['data']['title'] = cfg::$service[1]['title'];
      $data['data']['content'] = $res?:'Error';
      $this->send($uid, $data);
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