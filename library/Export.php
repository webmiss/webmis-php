<?php
namespace Library;

use Service\Base;
use Library\FileEo;
use Mpdf\Mpdf;

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
      'titleColor'=> '#020408',      //标题颜色
      'titleBgColor'=> '#F2F4F8',    //标题背景
    ],$param);
    // 内容
    $html = '<html>';
    $html .= '<style type="text/css">';
    $html .= 'table{font-family:Microsoft YaHei,微软雅黑,SimHei,helvetica,arial,verdana,tahoma,sans-serif;}';
    $html .= 'table td{height: 32px; padding: 10px; text-align: center; border: '.$param['borderColor'].' 1px solid;}';
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

  /* 导出PDF */
  static function PdfHmtl(string $path='upload/tmp/', string $filename='', string $html='', array $config=[]){
    // 参数
    $config = array_merge([
      'mode'=> 'UTF-8',               //编码
      'format'=> 'A4',                //纸张大小
      'default_font'=> '微软雅黑',    //字体
      'default_font_size'=> '14',     //文本大小
      'margin_top'=> '10',            //上边距
      'margin_bottom'=> '10',         //下边距
      'margin_left'=> '15',           //左边距
      'margin_right'=> '15',          //右边距
    ], $config);
    // 配置
    $pdf = new Mpdf($config);
    $pdf->autoScriptToLang = true;
    $pdf->autoLangToFont  = true;
    $pdf->WriteHTML($html);
    if(!$filename) return $pdf->Output();
    // 保存
    if(!FileEo::Mkdir($path)) return '无法访问目录"'.$path.'"';
    $file = $path.$filename;
    $pdf->Output($file, 'f');
    return $file;
  }

}