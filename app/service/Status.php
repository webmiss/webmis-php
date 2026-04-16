<?php
namespace App\Service;

class Status {

  /* 公共 */
  static function Public(string $name): array {
    $data = [
      'role_name'=> ['0'=>'用户', '1'=>'开发'],
      'status_name'=> ['0'=>'禁用', '1'=>'正常'],
    ];
    return $data[$name];
  }

}