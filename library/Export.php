<?php
namespace Library;

use Service\Base;
use Library\FileEo;

/* 导出 */
class Export extends Base {

  /* Excel */
  static function Excel(array $data=[], array $title=[], array $param=[]): string {
    $html = self::ExcelTop($param);
    if(!empty($title)) $html .= self::ExcelTitle($title);
    foreach($data as $v) $html .= self::ExcelData($v);
    $html .= self::ExcelBottom();
    return $html;
  }
  /* Excel-追加内容 */
  static function ExcelFileEnd(string $path='upload/tmp/', string $filename='', string $content=''){
    if(!FileEo::Mkdir($path)) return '无法访问目录"'.$path.'"';
    if(!$filename) $filename = date('YmdHis').mt_rand(1000, 9999).'.xlsx';
    return FileEo::WriterEnd($path.$filename, $content);
  }

  /* Excel-头部 */
  static function ExcelTop(array $param=[]){
    // 参数
    $param = array_merge([
      'borderColor'=>'#E2E4E8',      //边框颜色
      'titleColor'=> '#666',         //标题颜色
      'titleBgColor'=> '#F2F2F2',    //标题背景
    ],$param);
    // 内容
    $html = '<html>';
    $html .= '<style type="text/css">';
    $html .= 'table td{height: 32px; border: '.$param['borderColor'].' 1px solid;}';
    $html .= '.title{background-color: '.$param['titleBgColor'].'; color: '.$param['titleColor'].'; font-weight: bold;}';
    $html .= '</style>';
    $html .= '<table>';
    return $html;
  }
  /* Excel-标题 */
  static function ExcelTitle(array $row=[]){
    $html = '<tr>';
    foreach($row as $v) $html .= '<td class="title">'.$v.'</td>';
    $html .= '</tr>';
    return $html;
  }
  /* Excel-中间 */
  static function ExcelData(array $row=[]){
    $html = '<tr>';
    foreach($row as $v) $html .= '<td>'.$v.'</td>';
    $html .= '</tr>';
    return $html;
  }
  /* Excel-底部 */
  static function ExcelBottom(){
    $html = '</table>';
    $html .= '</html>';
    return $html;
  }

}