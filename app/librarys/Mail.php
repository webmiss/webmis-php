<?php
namespace App\Librarys;

use App\Config\Email;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/* 邮件 */
class Mail {

  /* 发送 */
  static function SmtpSend(array $param=[], string $server='default'): string {
    // 配置
    $cfg = Email::config($server);
    // 参数
    $param = array_merge([
      'from'=> $cfg['username'],    // 发件人
      'from_name'=> $cfg['name'],   // 发件人名称
      'to'=> '',                    // 收件人
      'to_name'=> '',               // 收件人名称
      'subject'=> '',               // 标题
      'content'=> '',               // 内容
      'isHtml'=> false,             // 是否Html
    ], $param);
    // 验证
    if(!$param['from']) return '请填写发件人';
    if(!$param['to']) return '请填写收件人';
    if(!$param['subject']) return '请填写邮件标题';
    if(!$param['content']) return '请填写邮件内容';
    // 邮件服务
    $mail = new PHPMailer(true);
    try {
      // 配置
      $mail->isSMTP();
      $mail->Host = $cfg['host'];
      $mail->SMTPAuth = $cfg['smtp_auth'];
      $mail->Username = $cfg['username'];
      $mail->Password = $cfg['password'];
      $mail->Port = $cfg['port'];
      $mail->CharSet = $cfg['charset'];
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      // 发件人
      $mail->setFrom($param['from'], $param['from_name']);
      // 收件人
      $mail->addAddress($param['to'], $param['to_name']); 
      // 内容
      if($param['isHtml']) $mail->isHTML(true);
      $mail->Subject = $param['subject'];
      $mail->Body = $param['content'];
      // 结果
      return $mail->send()?'':'发送失败';
    } catch (Exception $e) {
      return $mail->ErrorInfo;
    }
  }

}