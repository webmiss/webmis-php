<?php
namespace App\Librarys\Dian3;

use App\Config\Dian3;
use App\Util\Util;

/* 点三-签名 */
class Sign {

  /* 签名 */
  static function GetSign(array $param, array|string $data=''): array {
    // 配置
    $cfg = Dian3::Config();
    $param = array_merge([
      'method'=> 'ds.omni.erp.third.order.push',
      'appKey'=> $cfg->AppKey,
      'timestamp'=> Util::Time(),
    ], $param);
    ksort($param);
    // 拼接字符串
    $str = '';
    foreach($param as $k=>$v) if($v!=='') $str .= $k.$v;
    $str .= is_array($data)?json_encode($data):$data;
    $str = $cfg->AppSecret.$str.$cfg->AppSecret;
    $param['sign'] = md5($str);
    // 返回
    return [$param['sign'], http_build_query($param), $str];
  }

}