<?php
namespace App\Admin;

use Config\Env;
use Library\Export;
use Service\AdminToken;
use Service\Base;
use Service\Data;
use Util\Util;
use Model\ErpGoodsLogs;

class ErpGoodsLog extends Base {

  // 导出
  static private $export_path = 'upload/tmp/';         // 目录
  static private $export_filename = '';                // 文件名

  /* 统计 */
  static function Total(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, '');
    if($msg!='') return self::GetJSON(['code'=>4001, 'msg'=>$msg]);
    if(empty($data) || !is_array($data)) {
      return self::GetJSON(['code'=>4000, 'msg'=>'参数错误!']);
    }
    // 条件
    $param = $data?$data:[];
    list($where, $pname) = self::getWhere($param);
    // 统计
    $m = new ErpGoodsLogs();
    if($pname) $m->Partition($pname);
    $m->Columns('count(*) AS total');
    $m->Where($where);
    $one = $m->FindFirst();
    $total = [
      'total'=> $one?(int)$one['total']:0,
    ];
    // 返回
    return self::GetJSON(['code'=>0, 'msg'=>'成功', 'time'=>date('Y/m/d H:i:s'), 'data'=>$total]);
  }

  /* 列表 */
  static function List() {
    // 参数 
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    $page = self::JsonName($json, 'page');
    $limit = self::JsonName($json, 'limit');
    $order = self::JsonName($json, 'order');
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data) || empty($page) || empty($limit)) return self::GetJSON(['code'=> 4000]);
    // 条件
    $param = $data?$data:[];
    list($where, $pname) = self::getWhere($param);
    // 统计
    $m = new ErpGoodsLogs();
    if($pname) $m->Partition($pname);
    $m->Columns('id', 'sku_id', 'operator_id', 'operator_name', 'content', 'FROM_UNIXTIME(ctime) as ctime');
    $m->Where($where);
    $m->Page($page, $limit);
    $m->Order($order?:'ctime DESC, id DESC');
    $list = $m->Find();
    // 返回
    return self::GetJSON(['code'=>0, 'time'=>date('Y/m/d H:i:s'), 'data'=>$list]);
  }
  /* 搜索条件 */
  static private function getWhere(array $d): array {
    $where = [];
    // 时间
    $stime = isset($d['stime'])?trim($d['stime']):'';
    if($stime){
      $start = strtotime($stime.' 00:00:00');
      $where[] = 'ctime>='.$start;
    }
    $etime = isset($d['etime'])?trim($d['etime']):'';
    if($etime){
      $end = strtotime($etime.' 23:59:59');
      $where[] = 'ctime<='.$end;
    }
    // 分区
    $pname = '';
    if($stime && $etime){
      $stime = strtotime($stime);
      $etime = strtotime($etime);
      $pname = Data::PartitionName($stime, $etime);
    }
    // 关键字
    $key = isset($d['key'])?Util::Trim($d['key']):'';
    if($key){
      $arr = [
        'sku_id="'.$key.'"',
        'operator_name="'.$key.'"',
        'content like "%'.$key.'%"',
      ];
      $where[] = '('.implode(' OR ', $arr).')';
    }
    // 编码
    $sku_id = isset($d['sku_id'])?trim($d['sku_id']):'';
    if($sku_id){
      if(strstr($sku_id, '%')){
        $where[] = 'sku_id like "'.$sku_id.'"';
      }else{
        $arr = explode(' ', $sku_id);
        foreach($arr as $k=>$v) $arr[$k] = trim($v);
        $where[] = 'sku_id in("'.implode('","', $arr).'")';
      }
    }
    // 操作员
    $operator = isset($d['operator'])?trim($d['operator']):'';
    if($operator) $where[] = 'operator_name like "'.$operator.'"';
    // 内容
    $content = isset($d['content'])?trim($d['content']):'';
    if($content) $where[] = 'content like "%'.$content.'%"';
    return [implode(' AND ', $where), $pname];
  }

  /* 删除 */
  static function Del(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    // 验证
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data)) return self::GetJSON(['code'=> 4000]);
    // 数据
    $data = $data?$data:[];
    $ids = implode(',', $data);
    // 模型
    $m = new ErpGoodsLogs();
    $m->Where('id in('.$ids.')');
    return $m->Delete()?self::GetJSON(['code'=>0]):self::GetJSON(['code'=>5000]);
  }

  /* 导出 */
  static function Export(): string {
    // 参数
    $json = self::Json();
    $token = self::JsonName($json, 'token');
    $data = self::JsonName($json, 'data');
    $order = self::JsonName($json, 'order');
    // 验证权限
    $msg = AdminToken::Verify($token, $_SERVER['REQUEST_URI']);
    if($msg!='') return self::GetJSON(['code'=> 4001]);
    if(empty($data)) return self::GetJSON(['code'=> 4000]);
    // 条件
    $param = $data?$data:[];
    list($where, $pname) = self::getWhere($param);
    // 查询
    $m = new ErpGoodsLogs();
    $m->Columns('id', 'sku_id', 'operator_id', 'operator_name', 'content', 'FROM_UNIXTIME(ctime) as ctime');
    $m->Where($where);
    $m->Order($order?:'ctime DESC');
    $list = $m->Find();
    // 导出文件
    $admin = AdminToken::Token($token);
    self::$export_filename = 'GoodsLog_'.date('YmdHis').'_'.$admin->uid.'.xlsx';
    $html = Export::ExcelTop();
    $html .= Export::ExcelTitle([
      'ID', '时间', '编码', 'UID', '操作员', '内容'
    ]);
    // 数据
    foreach($list as $v){
      // 内容
      $html .= Export::ExcelData([
        $v['id'],
        '&nbsp;'.$v['ctime'],
        '&nbsp;'.$v['sku_id'],
        $v['operator_id'],
        $v['operator_name'],
        $v['wms_co_id'],
        $v['content'],
      ]);
    }
    $html .= Export::ExcelBottom();
    Export::ExcelFileEnd(self::$export_path, self::$export_filename, $html);
    // 返回
    return self::GetJSON(['code'=>0, 'data'=>['path'=>Env::BaseUrl(self::$export_path), 'filename'=>self::$export_filename]]);
  }
  
}
