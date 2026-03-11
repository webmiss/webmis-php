<?php
namespace App\Librarys\Dian3;

use App\Service\Logs;
use App\Config\Dian3;
use App\Librarys\Curl;
use App\Util\Util;
use Mpdf\Tag\S;

/* 点三-订单数据 */
class Order {

  //正式环境
  static private $Url = 'http://open_3rd.product.diansan.com/open/oms/router';
  static private $Header = [];

  /* 签名-URL */
  static function GetUrlSign(array $param): array {
    // 参数
    $cfg = Dian3::Config();
    $param['appKey'] = $cfg->AppKey;
    $param['timestamp'] = Util::Time();
    ksort($param);
    // 加密字符串
    $str = '';
    foreach($param as $k=>$v) $str .= $k.$v;
    $str = $cfg->AppSecret.$str.$cfg->AppSecret;
    // 签名
    $param['sign'] = strtoupper(md5($str));
    // 返回
    return [$param, http_build_query($param)];
  }

  /* 获取数据 */
  static function GetData(string $url, array $data, string $method='POST', string $type='json') {
    // 签名结果
    list($sign, $urlParam) = Sign::GetSign([
      'appKey'=> '',
      'method'=> $url,
      'timestamp'=> Util::Time(),
    ], $data);
    if($type=='json'){
      $data = $data?json_encode($data):'';
      self::$Header['Content-Type'] = 'application/json;charset=UTF-8;';
    }
    // 请求
    $stime = date('Y-m-d H:i:s');
    $res = Curl::Request(self::$Url.'?'.$urlParam, $data, $method, self::$Header);
    if(isset($res->response->success)){
      return isset($res->response->data)?$res->response->data:$res;
    }else{
      Logs::File('upload/logs/ErrDian3('.date('m-d').').json', ['stime'=>$stime, 'etime'=>date('Y-m-d H:i:s'), 'url'=>self::$Url.$method, 'data'=>$data, 'response'=>$res]);
      return isset($res->response)?$res->response:'未知错误!';
    }
  }

  /* 订单-查询 */
  static function OrderQuery(array $params=[]) {
    $url = 'ds.omni.erp.third.order.query';
    // 参数
    $params = array_merge([
      'pageNo'=> 1,            //页码
      'pageSize'=> 100,        //页数
      'timeType'=> 1,          //时间类型: 1平台更新时间、2点三创建时间
      'startTime'=> date('Y-m-d'.' 00:00:00'),     //开始时间
      'endTime'=> date('Y-m-d'.' 23:59:59'),       //结束时间
    ], $params);
    return self::GetData($url, $params);
  }

  /* 订单-修改备注、旗帜 */
  static function OrderMemoUpdate(array $params=[]) {
    $url = 'ds.omni.erp.third.order.memo.update';
    // 参数
    $params = array_merge([
      'posCode'=> '',               //店铺编码
      'refOid'=> '',                //订单编号
      'memo'=> '',                  //客服备注
      'flag'=> '',                  //旗帜
    ], $params);
    return self::GetData($url, $params);
  }

  /* 订单-发货 */
  static function OrderSend(array $params=[]) {
    $url = 'ds.omni.erp.third.order.query';
    // 参数
    $params = array_merge([
      'posCode'=> '',               //店铺编码
      'refOid'=> '',                //订单编号
      'packages'=>[[
        'companyCode'=>'',          //商家物流编码
        'outSid'=>0,                //物流单号
      ]]
    ], $params);
    return self::GetData($url, $params);
  }

  /* 物流-查询 */
  static function logisticsQuery(array $params=[]) {
    $url = 'ds.omni.erp.logistics.partner.query';
    // 参数
    $params = array_merge([
      'name'=> '',              //物流名称
      'code'=> '',              //物流编码
      'pageNo'=> 1,             //页码
      'pageSize'=> 100,         //数量
      'status'=> 'VALID',       //类型: 有效(VALID)、禁用(INVALID)、删除(DELETED)
      'posCode'=> '',           //店铺编码
    ], $params);
    return self::GetData($url, $params);
  }

  /* 电子面单-获取 */
  static function getWaybill(array $params=[]) {
    $url = 'ds.omni.erp.waybill.third.get';
    // 参数
    $params = array_merge([
      'cpCode'=> time(),        //OMS商家物流编码
    ], $params);
    return self::GetData($url, $params);
  }

  /* 电子面单-回收 */
  static function cancelWaybill(array $params=[]) {
    $url = 'ds.omni.erp.waybill.third.cancel';
    // 参数
    $params = array_merge([
      'waybillCode'=> '',        //面单号
    ], $params);
    return self::GetData($url, $params);
  }

  /* 抖音BIC-QIC订单码 */
  static function getQic(array $params=[]) {
    $url = 'ds.dy.qic.code.get';
    // 参数
    $params = array_merge([
      'posCode'=> '',        //店铺编码
      'request'=>[
        'orderIds'=>[]       //订单号
      ]
    ], $params);
    return self::GetData($url, $params);
  }

  /* 抖音BIC-商家发货 */
  static function bindOrderCode(array $params=[]) {
    $url = 'ds.dy.qic.code.bind';
    // 参数
    $params = array_merge([
      'posCode'=> '',               //店铺编码
      'request'=>[
        'shopPackageId'=>'',        //商家包裹ID
        'deliveryType'=>0,          //送检方式
        'shipType'=>3,              //出仓方式
        'orderList'=>[
          [
            'orderId'=>0,           //订单ID
            'orderCode'=>'',        //订单码
          ]
        ]
      ]
    ], $params);
    return self::GetData($url, $params);
  }

}