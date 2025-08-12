<?php
namespace Library\Jushuitan;

use Service\Base;
use Config\Jushuitan;
use Library\Curl;
use Library\Redis;
use Service\Logs;

/* 聚水谭ERP */
class Erp extends Base {

  static private $Url = 'https://openapi.jushuitan.com/open';  //正式环境
  // static private $Url = 'https://dev-api.jushuitan.com/open';  //沙箱环境

  /* 签名 */
  static function GetSign(array $data): string {
    $cfg = Jushuitan::Erp();
    ksort($data);
    // 加密字符串
    $signStr = '';
    foreach($data as $k=>$v) $signStr .= $k.$v;
    $signStr = $cfg->AppSecret.$signStr;
    return strtolower(md5($signStr));
  }

  /* 获取Code */
  static function GetCode(): string {
    $cfg = Jushuitan::Erp();
    $data = [
      'app_key'=> $cfg->AppKey,
      'timestamp'=> time(),
      'charset'=> 'utf-8',
    ];
    $data['sign'] = self::GetSign($data);
    $dataStr = Curl::UrlEncode($data);
    return 'https://openweb.jushuitan.com/auth?'.$dataStr;
  }

  /* Token-获取 */
  static function GetToken(string $code=''): object {
    $cfg = Jushuitan::Erp();
    if($code){
      // 获取
      $data = [
        'app_key'=> $cfg->AppKey,
        'timestamp'=> time(),
        'grant_type'=> 'authorization_code',
        'charset'=> 'utf-8',
        'code'=> $code,
      ];
      $data['sign'] = self::GetSign($data);
      $dataStr = Curl::UrlEncode($data);
      $res = Curl::Request('https://openapi.jushuitan.com/openWeb/auth/accessToken', $dataStr, 'POST');
      if($res->code!=0) return $res;
      // 保存Token
      self::SaveToken($res->data);
      return $res->data;
    }else{
      // 缓存
      $redis = new Redis();
      $access_token = $redis->Gets($cfg->access_token);
      $refresh_token = $redis->Gets($cfg->refresh_token);
      $time = $redis->Ttl($cfg->access_token);
      if($time>3600) return (object)['access_token'=>$access_token, 'expires_in'=>$time, 'refresh_token'=>$refresh_token];
      // 刷新
      $data = [
        'app_key'=> $cfg->AppKey,
        'timestamp'=> time(),
        'grant_type'=> 'refresh_token',
        'charset'=> 'utf-8',
        'refresh_token'=> $refresh_token,
        'scope'=> 'all',
      ];
      $data['sign'] = self::GetSign($data);
      $dataStr = Curl::UrlEncode($data);
      $res = Curl::Request('https://openapi.jushuitan.com/openWeb/auth/refreshToken', $dataStr, 'POST');
      if($res->code!=0) return $res;
      // 保存Token
      self::SaveToken($res->data);
      return $res->data;
    }
  }
  /* Token-缓存 */
  static function SaveToken($data) {
    $cfg = Jushuitan::Erp();
    $redis = new Redis();
    $redis->Set($cfg->access_token, $data->access_token);
    $redis->Set($cfg->refresh_token, $data->refresh_token);
    $redis->Expire($cfg->access_token, $cfg->refresh_time);
    $redis->Close();
  }
  
  /* 公共配置 */
  static function GetData(string $method, array $biz, bool $log=false) {
    // 获取Token
    $token = self::GetToken();
    // 基础数据
    $cfg = Jushuitan::Erp();
    ksort($biz);
    $data = [
      'app_key'=> $cfg->AppKey,
      'access_token'=> $token->access_token,
      'timestamp'=> time(),
      'charset'=> 'utf-8',
      'version'=> '2',
      'biz'=> json_encode($biz),
    ];
    $data['sign'] = self::GetSign($data);
    // 请求
    $stime = date('Y-m-d H:i:s');
    $dataStr = Curl::UrlEncode($data);
    $res = Curl::Request(self::$Url.$method, $dataStr, 'POST');
    if($log) Logs::File('upload/erp/log('.date('m-d').').json', ['stime'=>$stime, 'etime'=>date('Y-m-d H:i:s'), 'url'=>self::$Url.$method, 'data'=>$dataStr, 'response'=>$res]);
    if(isset($res->code) && $res->code===0){
      return isset($res->data)?$res->data:'暂无数据!';
    }else{
      Logs::File('upload/erp/Err('.date('m-d').').json', ['stime'=>$stime, 'etime'=>date('Y-m-d H:i:s'), 'url'=>self::$Url.$method, 'data'=>$data, 'response'=>$res]);
      return isset($res->msg)?$res->msg:'未知错误!';
    }
  }

  /* 店铺-查询 */
  static function GetShop(array $params=[]){
    $method = '/shops/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,        //页码
      'page_size'=>100,        //每页条数
    ],$params);
    return self::GetData($method, $param);
  }

  /* 仓库-查询 */
  static function GetPartner(array $params=[]){
    $method = '/wms/partner/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,        //页码
      'page_size'=>100,        //每页条数
    ],$params);
    return self::GetData($method, $param);
  }

  /* 供应商-查询 */
  static function GetSupplier(array $params=[]){
    $method = '/supplier/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,        //页码
      'page_size'=>500,       //每页条数
      'modified_begin'=>'',   //起始时间: Y-m-d H:i:s
      'modified_end'=>'',     //结束时间: Y-m-d H:i:s
      'supplier_codes'=>[],   //供应商编码: 最大50条
    ],$params);
    return self::GetData($method, $param);
  }
  /* 商品-上传 */
  static function UploadSupplier(array $biz=[]){
    $method = '/supplier/upload';
    return self::GetData($method, $biz);
  }

  /* 商品-查询(sku) */
  static function GetSku(array $params=[]){
    $method = '/sku/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,                //页码
      'page_size'=>3,                 //每页条数
      'modified_begin'=>'',           //起始时间: Y-m-d H:i:s
      'modified_end'=>'',             //结束时间: Y-m-d H:i:s
      'sku_ids'=>'',                  //商品编码: 逗号隔开,最多20 20220526G010001
    ],$params);
    return self::GetData($method, $param);
  }
  /* 商品-上传 */
  static function UploadSku(array $biz=[]){
    $method = '/jushuitan/itemsku/upload';
    return self::GetData($method, $biz);
  }

  /* 库存-查询 */
  static function GetInventory(array $params=[]){
    $method = '/inventory/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,                //页码
      'page_size'=>50,                 //每页条数
      'modified_begin'=>'',           //起始时间: Y-m-d H:i:s
      'modified_end'=>'',             //结束时间: Y-m-d H:i:s
      'wms_co_id'=>0,                 //分仓编号
      'sku_ids'=>'',                  //商品编码: 逗号隔开,最多20 20220526G010001
      'has_lock_qty'=>true,           //库存锁定数
    ],$params);
    return self::GetData($method, $param);
  }

  /* 订单-查询 */
  static function GetOrdersList(array $params=[]){
    $method = '/orders/single/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,        //页码
      'page_size'=>100,       //每页条数
      'modified_begin'=>'',   //起始时间: Y-m-d H:i:s
      'modified_end'=>'',     //结束时间: Y-m-d H:i:s
      'so_ids'=>[],           //线上订单号
    ],$params);
    return self::GetData($method, $param);
  }

  /* 采购-入库 */
  static function PurchaseIn(array $biz=[]){
    $method = '/jushuitan/purchasein/upload';
    return self::GetData($method, $biz);
  }
  /* 采购-入库(批量) */
  static function PurchaseInAll(array $biz=[]){
    $method = '/webapi/wmsapi/purchasein/createbatch';
    return self::GetData($method, $biz);
  }

  /* 采购-退货 */
  static function PurchaseOut(array $biz=[]){
    $method = '/jushuitan/purchaseout/upload';
    return self::GetData($method, $biz);
  }
  /* 采购-退货(批量) */
  static function PurchaseOutAll(array $biz=[]){
    $method = '/webapi/wmsapi/purchaseout/createbatch';
    return self::GetData($method, $biz);
  }

  /* 调拨-查询 */
  static function Allocate(array $params=[]){
    $method = '/allocate/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,                //页码
      'page_size'=>50,                //每页条数
      'modified_begin'=>'',           //起始时间: Y-m-d H:i:s
      'modified_end'=>'',             //结束时间: Y-m-d H:i:s
      'so_ids'=>[],                   //线上订单号
    ],$params);
    return self::GetData($method, $param);
  }
  /* 调拨-跨仓 */
  static function AllocateKC(array $biz=[]){
    $method = '/allocate/kc/upload';
    return self::GetData($method, $biz);
  }
  /* 调拨-确认 */
  static function AllocateConfirm(array $biz=[]){
    $method = '/jushuitan/allocate/confirm';
    return self::GetData($method, $biz);
  }

  /* 出库-销售 */
  static function GetOrders(array $params=[]){
    $method = '/orders/out/simple/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,        //页码
      'page_size'=>100,       //每页条数
      'modified_begin'=>'',   //起始时间: Y-m-d H:i:s
      'modified_end'=>'',     //结束时间: Y-m-d H:i:s
      'so_ids'=>[],           //线上订单号
      'status'=>'Confirmed',  //状态: WaitConfirm(待出库)、Confirmed(已出库)、Cancelled(取消)、Delete(作废)
    ],$params);
    return self::GetData($method, $param);
  }
  
  /* 售后-退货退款 */
  static function GetRefund(array $params=[]){
    $method = '/refund/single/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,        //页码
      'page_size'=>50,        //每页条数
      'modified_begin'=>'',   //起始时间: Y-m-d H:i:s
      'modified_end'=>'',     //结束时间: Y-m-d H:i:s
      'so_ids'=>[],           //线上订单号
      'shop_buyer_ids'=>[],   //买家账号: 最多50
      // 'status'=>'Confirmed',  //状态: WaitConfirm(待确认)、Confirmed(已确认)、Cancelled(作废)、Merged(被合并)
      'good_status'=>'SELLER_RECEIVED',  //状态: BUYER_NOT_RECEIVED(未收到)、BUYER_RECEIVED(买家已收到)、BUYER_RETURNED_GOODS(买家已退货)、SELLER_RECEIVED(卖家已收到退货)
    ],$params);
    return self::GetData($method, $param);
  }

  /* 其它出入库-查询 */
  static function GetOtherQuery(array $params=[]){
    $method = '/other/inout/query';
    // 参数
    $param = array_merge([
      'page_index'=>1,        //页码
      'page_size'=>50,        //每页条数
      'modified_begin'=>'',   //起始时间: Y-m-d H:i:s
      'modified_end'=>'',     //结束时间: Y-m-d H:i:s
      'status'=>'Confirmed',  //状态: WaitConfirm(待审核)、Confirmed(生效)、Cancelled(取消)、Archive(归档)
      'wms_co_id'=>0,         //分仓编号
      'so_ids'=>[],           //线上订单号
      'io_ids'=>[],           //出仓单号
    ],$params);
    return self::GetData($method, $param);
  }
  /* 其它出入库-上传 */
  static function OtherUpload(array $biz=[]){
    $method = '/jushuitan/otherinout/upload';
    return self::GetData($method, $biz);
  }

  /* 库存盘点-上传 */
  static function InventoryUpload(array $biz=[]){
    $method = '/jushuitan/inventoryv2/upload';
    return self::GetData($method, $biz);
  }

}