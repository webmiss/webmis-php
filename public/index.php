<?php

/* 常量 */
define('BASE_PATH', __DIR__);
define('STDERR',fopen('php://stderr', 'a'));

/* Composer */
$composer = BASE_PATH.'/../vendor/autoload.php';
if(!is_file($composer)) die('安装依赖包: composer install'."\n");
require $composer;

/* 错误 */
$mode = App\Config\Env::$mode;
if($mode==='dev') {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
} else {
  error_reporting(E_ERROR | E_PARSE);
  ini_set('display_errors', 1);
}

/* 路由 */
try {
  $router = new Core\Router();
  $router->run();
} catch (\Exception $e) {
  echo $e->getMessage()."\n";
}