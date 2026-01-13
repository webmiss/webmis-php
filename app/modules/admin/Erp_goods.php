<?php

namespace App\Admin;

use Core\Controller;
use App\Config\Env;
use App\Service\TokenAdmin;
use App\Service\Data;
use App\Service\Goods;
use App\Service\Logs;
use App\Service\Status;
use App\Librarys\Export;
use App\Util\Util;
use App\Librarys\Qrcode;
use Milon\Barcode\DNS1D;

use App\Model\ErpGoodsLogs;
use App\Model\ErpPurchaseStock;

use App\Model\ErpBasePartner;
use App\Model\ErpBaseBrand;
use App\Model\ErpBaseCategory;

/* 控制台 */
class ErpGoods extends Controller {

  // 导出
  static private $export_path = 'upload/tmp/';  // 目录
  static private $export_filename = '';         // 文件名

  /* 商品-标签 */
  static function Barcode(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $sku = self::JsonName($json, 'sku');
    // 验证权限
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($sku)) return self::GetJSON(['code'=>4000]);
    // 商品编码
    $list = [];
    foreach ($sku as $k=>$sku_id) {
      $sku_id = strtoupper(Util::Trim($sku_id));
      $sku[$k] = $sku_id;
      // 条形码
      $d = new DNS1D();
      $d->setStorPath('upload/tmp');
      $list[$sku_id]['barcode'] = 'data:image/png;base64,'.$d->getBarcodePNG($sku_id, 'C128', 9, 360);
      // 二维码
      $qrcode = Qrcode::Create(['text'=>$sku_id]);
      $list[$sku_id]['qrcode'] = 'data:image/png;base64,'.base64_encode($qrcode);
    }
    // 商品资料
    $info = Goods::GoodsInfoAll($sku, 'data', [-1, 3], [
      'sku_id', 'name', 'short_name', 'properties_value', 'unit', 'weight',
      'purchase_price', 'sale_price', 'market_price',
      'ratio', 'ratio_purchase', 'ratio_sale', 'ratio_market',
      'brand', 'owner', 'i_id'
    ]);
    foreach($list as $k=>$v) {
      if(isset($info[$k])) {
        $v = array_merge($info[$k], $v);
        $v['is_goods'] = true;
      } else {
        $v['is_goods'] = false;
      }
      $list[$k] = $v;
    }
    // 返回
    return self::GetJSON(['code'=> 0, 'time'=> date('Y/m/d H:i:s'), 'data'=> $list]);
  }

  /* 商品-标签统计 */
  static function BarcodePrint(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $template = self::JsonName($json, 'template');
    $num = self::JsonName($json, 'num');
    // 验证权限
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    // 用户信息
    $admin = TokenAdmin::Token($token);
    Logs::Goods([
      'ctime'=> time(),
      'operator_id'=> $admin->uid,
      'operator_name'=> $admin->name,
      'sku_id'=> $template,
      'content'=> '打印标签: '.$template.' 数量: '.$num
    ]);
    return self::GetJSON(['code'=>0]);
  }

  /* 商品-资料 */
  static function Info(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $sku_id = self::JsonName($json, 'sku_id');
    // 验证权限
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($sku_id)) return self::GetJSON(['code'=>4000]);
    // 商品资料
    $sku_id = strtoupper(Util::Trim($sku_id));
    $info = self::getGoodsInfo($sku_id);
    if(!$info) return self::GetJSON(['code'=>4010]);
    // 封面图
    $info['img'] = $info['img']?Data::ImgGoods($info['sku_id'], false):'';
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>$info]);
  }
  /* 商品信息 */
  private static function getGoodsInfo(string $sku_id): array {
    return Goods::GoodsInfo($sku_id, 'data', [
      'sku_id', 'i_id', 'name', 'short_name', 'properties_value', 'unit', 'weight',
      'sale_price', 'market_price', 'num',
      'ratio', 'ratio_sale', 'ratio_market',
      'labels', 'category', 'brand', 'owner', 'img',
      'FROM_UNIXTIME(ctime) as ctime', 'FROM_UNIXTIME(utime) as utime'
    ]);
  }

  /* 商品-流向 */
  static function Direct(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $sku_id = self::JsonName($json, 'sku_id');
    $time = self::JsonName($json, 'time');
    // 验证权限
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($sku_id)) return self::GetJSON(['code'=>4000]);
    // 商品编码
    $sku_id = strtoupper(Util::Trim($sku_id));
    $time = $time?(int)$time+1:3+1;
    list($total, $list) = Goods::GoodsWork($sku_id, [-1, $time]);
    return self::GetJSON(['code'=>0, 'time'=> date('Y/m/d H:i:s'), 'data'=>['total'=>$total, 'list'=>$list]]);
  }
  /* 商品-流向 */
  static function DirectExport(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $sku_id = self::JsonName($json, 'sku_id');
    $time = self::JsonName($json, 'time');
    // 验证权限
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($sku_id)) return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    // 商品编码
    $sku_id = strtoupper(Util::Trim($sku_id));
    $time = $time?(int)$time+1:3+1;
    list($total, $list) = Goods::GoodsWork($sku_id, [-1, $time]);
    // 导出文件
    $admin = TokenAdmin::Token($token);
    self::$export_filename = 'GoodsDirect_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', '日期', '类型', '仓库', '标签价(元)', '吊牌价(W)', '数量', '折扣', '品牌', '采购员', '状态', '创建时间', '更新时间', '制单员', '审核员', '备注'
    ]);
    foreach($list as $v) {
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        substr($v['ctime'], 0, 10),
        $v['type_name'],
        $v['warehouse'],
        $v['sale_price']*$v['ratio'],
        $v['market_price']*$v['ratio'],
        $v['num'],
        $v['ratio'],
        $v['brand'],
        $v['owner'],
        $v['state']=='1'?'完成':'进行中',
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['utime'],
        $v['creator'],
        $v['operator'],
        $v['remark'],
      ]);
    }
    $html .= Export::ExcelBottom();
    Export::ExcelFileEnd(self::$export_path, self::$export_filename, $html);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['path'=>self::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]]);
  }

  /* 商品-分仓库存 */
  static function Stock(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $sku_id = self::JsonName($json, 'sku_id');
    $time = self::JsonName($json, 'time');
    // 验证权限
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($sku_id)) return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    // 商品编码
    $sku_id = strtoupper(Util::Trim($sku_id));
    // 查询
    $m = new ErpPurchaseStock();
    $m->Columns('id', 'sku_id', 'wms_co_id', 'num', 'FROM_UNIXTIME(ctime) AS ctime', 'FROM_UNIXTIME(utime) AS utime');
    $m->Where('sku_id=?', $sku_id);
    $m->Order('utime DESC');
    $stock = $m->Find();
    $partner = ErpBasePartner::GetList();
    foreach ($stock as $k=>$v) $stock[$k]['wms_co_name'] = $partner[$v['wms_co_id']]['name'];
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'time'=>date('Y/m/d H:i:s'), 'data'=>$stock]);
  }

  /* 商品-日志 */
  static function Logs(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $sku_id = self::JsonName($json, 'sku_id');
    $time = self::JsonName($json, 'time');
    // 验证权限
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($sku_id)) return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    // 商品编码
    $sku_id = strtoupper(Util::Trim($sku_id));
    $time = $time?(int)$time+1:3+1;
    // 分区
    $pname = Data::PartitionSku($sku_id, [-1, $time]);
    $m = new ErpGoodsLogs();
    if($pname) $m->Partition($pname);
    $m->Columns('id', 'operator_id', 'operator_name', 'sku_id', 'content', 'FROM_UNIXTIME(ctime) as ctime');
    $m->Where('sku_id=?', $sku_id);
    $m->Order('ctime DESC');
    $list = $m->Find();
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'time'=>date('Y/m/d H:i:s'), 'data'=>$list]);
  }

  /* 封面图-上传 */
  static function UpImg(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $sku_id = self::JsonName($json, 'sku_id');
    $file = isset($_FILES['file'])?$_FILES['file']:[];
    // 验证
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($sku_id) || empty($file)) return self::GetJSON(['code'=>4000]);
    // 权限
    $admin = TokenAdmin::Token($token);
    if(!in_array($admin->type, [2, 4, 9])) return self::GetJSON(['code'=>4002]);
    // 查询
    $sku_id = strtoupper(Util::Trim($sku_id));
    $info = Goods::GoodsInfo($sku_id);
    if(!$info) return self::GetJSON(['code'=>4010]);
    // 更新
    $file = Goods::GoodsImgState([
      'type'=> 'update',
      'sku_id'=> $info['sku_id'],
      'user_uid'=> $admin->uid,
      'user_name'=> $admin->name,
      'source'=> 'PC',
      'file'=> $file,
    ]);
    if(!$file) self::GetJSON(['code'=>5000]);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['oss_url'=>Env::$img_url, 'file'=>$file]]);
  }

  /* 封面图-删除 */
  static function RemoveImg(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $sku_id = self::JsonName($json, 'sku_id');
    // 验证
    $msg = TokenAdmin::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001]);
    if(empty($sku_id)) return self::GetJSON(['code'=>4000]);
    // 权限
    $admin = TokenAdmin::Token($token);
    if(!in_array($admin->type, [2, 4, 9])) return self::GetJSON(['code'=>4002]);
    // 查询
    $sku_id = strtoupper(Util::Trim($sku_id));
    $info = Goods::GoodsInfo($sku_id);
    if(!$info) return self::GetJSON(['code'=>4010]);
    // 更新
    $res = Goods::GoodsImgState([
      'type'=> 'remove',
      'sku_id'=> $info['sku_id'],
      'user_uid'=> $admin->uid,
      'user_name'=> $admin->name,
      'source'=> 'PC',
    ]);
    // 返回
    return $res===true?self::GetJSON(['code'=>0]):self::GetJSON(['code'=>5000]);
  }
  
}
