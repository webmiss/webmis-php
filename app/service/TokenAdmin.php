<?php
namespace App\Service;

use Core\Base;
use Core\Redis;
use App\Config\Env;
use App\Librarys\Safety;
use App\Model\SysMenu;

/* Token Admin */
class TokenAdmin extends Base {

  /* 验证 */
  static function Verify(string $token, string $urlPerm): string {
    // Token
    if($token=='') return 'Token不能为空!';
    $tData = Safety::Decode($token);
    if(!$tData) return 'Token验证失败!';
    // 是否过期
    $uid = (string)$tData->uid;
    $key = Env::$admin_token_prefix.'_token_'.$uid;
    $redis = new Redis();
    $time = $redis->Ttl($key);
    if($time<1) return '请重新登录!';
    // 单点登录
    $access_token = $redis->Get($key);
    if(Env::$admin_token_sso && md5($token)!=$access_token) return '强制退出!';
    // 是否续期
    if(Env::$admin_token_auto){
      $redis->Expire($key, Env::$admin_token_time);
      $redis->Expire(Env::$admin_token_prefix.'_perm_'.$uid, Env::$admin_token_time);
    }
    // URL权限
    if($urlPerm=='') return '';
    $arr = explode('/', $urlPerm);
    $action = explode('?', end($arr))[0];
    array_pop($arr);
    $controller = implode('/', $arr);
    // 查询菜单
    $m = new SysMenu();
    $m->Columns('id', 'action');
    $m->Where('controller=?', $controller);
    $data = $m->FindFirst();
    if(empty($data)) return '菜单验证无效!';
    // 验证菜单
    $id = (string)$data['id'];
    $perm = self::GetPerm($token);
    if(!isset($perm[$id])) return '无权访问菜单!';
    // 验证动作
    $permVal = 0;
    $actionVal = (int)$perm[$id];
    $permArr = json_decode($data['action']);
    foreach($permArr as $v){
      if($action==$v->action){
        $permVal = (int)$v->perm;
        break;
      }
    }
    if(($actionVal&$permVal)==0) return '无权访问动作!';
    return '';
  }

  /* 权限-保存 */
  static function SavePerm(string $uid, string $perm): bool {
    $key = Env::$admin_token_prefix.'_perm_'.$uid;
    $redis = new Redis();
    $redis->Set($key, $perm);
    $redis->Expire($key, Env::$admin_token_time);
    return true;
  }
  
  /* 权限-获取 */
  static function GetPerm(string $token): array {
    $arr = [];
    // Token
    if($token=='') return $arr;
    $tData = Safety::Decode($token);
    if(!$tData) return $arr;
    // 权限
    $uid = (string)$tData->uid;
    $redis = new Redis();
    $permStr = $redis->Get(Env::$admin_token_prefix.'_perm_'.$uid);
    if(empty($permStr)) return $arr;
    // 拆分
    $perm = explode(' ', $permStr);
    foreach($perm as $v){
      $tmp = explode(':', $v);
      $arr[$tmp[0]] = (int)$tmp[1];
    }
    return $arr;
  }

  /* 生成 */
  static function Create(array $data): ?string {
    // 登录时间
    $data['l_time'] = date('Y-m-d H:i:s');
    $token = Safety::Encode($data);
    // 缓存Token
    $redis = new Redis();
    $key = Env::$admin_token_prefix.'_token_'.$data['uid'];
    $redis->Set($key, md5($token));
    $redis->Expire($key, Env::$admin_token_time);
    return $token;
  }

  /* 解析 */
  static function Token(string $token): ?object {
    $data = Safety::Decode($token);
    if(!$data) return null;
    // 过期时间
    $redis = new Redis();
    $data->time = $redis->Ttl(Env::$admin_token_prefix.'_token_'.$data->uid);
    return $data;
  }

}