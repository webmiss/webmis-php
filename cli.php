<?php
/* 常量 */
define('BASE_PATH', __DIR__);

/* Composer */
$composer = BASE_PATH.'/vendor/autoload.php';
if(!is_file($composer)) die('安装依赖包: composer install'."\n");
require $composer;

/* 错误 */
$mode = App\Config\Env::$mode_cli;
if($mode==='dev') {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
} else {
  error_reporting(E_ERROR | E_PARSE);
  ini_set('display_errors', 1);
}

/* 路由 */
try {
  $args = array();
  $router = new Core\RouterCli($argv);
  $router->run();
} catch (\Exception $e) {
  echo $e->getMessage();
}

// exit;

// try {
//   /* 参数 */
//   $args = array();
//   $c = isset($argv[1])?$argv[1]:'Main';
//   $a = isset($argv[2])?$argv[2]:'Index';
//   $p = isset($argv[3])?$argv[3]:'';

//   /* 实例化 */
//   $c = 'App\\Task\\'.$c;
//   if(!class_exists($c)){
//     throw new \InvalidArgumentException('类：" '.$c.' "不存在！');
//   }
//   $app = new $c();
//   // 是否存在函数
//   if(!method_exists($app, $a)){
//     throw new \InvalidArgumentException('函数：" '.$a.' "不存在！');
//   }
//   $res = $p?$app->$a($p):$app->$a();
//   echo $res."\n";
// } catch (\Exception $e) {
//   echo $e->getMessage()."\n";
// }
