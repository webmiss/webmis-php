<?php
namespace App\Service;

/* 状态 */
class Status {

  /* 公共 */
  static function Public(string $name): array {
    $data = [];
    switch ($name) {
      case 'role_name':
        $data = ['0'=>'用户', '1'=>'开发'];
        break;
      case 'status_name':
        $data = ['0'=>'禁用', '1'=>'正常'];
        break;
    }
    return $data;
  }

}