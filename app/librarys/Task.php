<?php
namespace App\Librarys;

/* 任务 */
class Task {

  /* 进程 */
  static function Popen(string $cli, array $data=[], bool $sync=false, $error='> /dev/null 2>&1') {
    $handle = popen($cli.' \''.json_encode($data).'\' '.$error.' &', 'r');
    if(!$sync) return $handle?pclose($handle):0;
    $output = stream_get_contents($handle);
    pclose($handle);
    return $output;
  }

}