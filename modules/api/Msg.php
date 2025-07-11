<?php
namespace App\Api;

use Service\Base;
use Service\ApiToken;
use Data\Msg as MsgD;
use Library\Aliyun\Oss;

/* 消息 */
class Msg extends Base {

  /* 列表 */
	static function List(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    // 验证
    $msg = ApiToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    // 数据
    $admin = ApiToken::Token($token);
    list($num, $list) = MsgD::GetList($admin->uid);
    return self::GetJSON(['code'=>0, 'data'=>['num'=>$num, 'list'=>$list]]);
  }

  /* 详情 */
	static function Show(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $gid = self::JsonName($json, 'gid');
    $fid = self::JsonName($json, 'fid');
    $page = self::JsonName($json, 'page');
    $limit = self::JsonName($json, 'limit');
    // 验证
    $msg = ApiToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(!is_numeric($gid) || !is_numeric($fid) || empty($page) || empty($limit)) return self::GetJSON(['code'=> 4000]);
    // 数据
    $admin = ApiToken::Token($token);
    $list = MsgD::GetShow($gid, $fid, $admin->uid, $page, $limit);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>$list]);
  }

  /* 搜索 */
	static function Sea(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $key = self::JsonName($json, 'key');
    // 验证
    $msg = ApiToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($key)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 数据
    $admin = ApiToken::Token($token);
    $list = MsgD::SeaUser($admin->uid, $key);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>$list]);
  }

  /* 阅读 */
	static function Read(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $ids = self::JsonName($json, 'ids');
    // 验证
    $msg = ApiToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(!is_array($ids) || empty($ids)) {
      return self::GetJSON(['code'=>4000]);
    }
    // 更新
    $admin = ApiToken::Token($token);
    $res = MsgD::Read($admin->uid, $ids);
    // 返回
    return $res?self::GetJSON(['code'=>0]):self::GetJSON(['code'=>5000]);
  }

  /* 清空 */
	static function Del(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $gid = self::JsonName($json, 'gid');
    $fid = self::JsonName($json, 'fid');
    // 验证
    $msg = ApiToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 更新
    $admin = ApiToken::Token($token);
    $res = MsgD::Del($gid, $fid, $admin->uid);
    // 返回
    return $res?self::GetJSON(['code'=>0]):self::GetJSON(['code'=>5000]);
  }

  /* Oss签名直传 */
  static function OssSgin(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $filename = self::JsonName($json, 'filename');
    // 验证
    $msg = ApiToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    // 文件
    $file = 'msg/'.date('Y').'/'.date('m').'/'.date('d').'/'.date('YmdHis').rand(1000,9999);
    // 后缀
    $ext = '';
    $arr = explode('.', $filename);
    if(count($arr)>1) $ext = $arr[count($arr)-1];
    if($ext) $file .= '.'.$ext;
    // 签名
    $res = Oss::Policy($file);
    $res['ext'] = $ext;
    return self::GetJSON(['code'=>0, 'data'=>$res]);
  }

}