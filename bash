#!/bin/bash

# 配置
ip="127.0.0.1"
port="9000"
cli="cli.php"
s=$1

# 运行
if [ "$s" == "serve" ]; then
  {
    php -S $ip:$port -t public
  } || {
    echo "> 请安装'php'"
  }
# 安装
elif [ "$s" == "install" ]; then
  {
    rm -fr composer.lock && composer install
  } || {
    echo "> 请安装'composer'"
  }
# Socket-运行
elif [ "$s" == "socket" ]; then
  {
    php $cli Socket start
  } || {
    echo "> 请安装'php'"
  }
# Socket-启动
elif [ "$s" == "socketStart" ]; then
  nohup php $cli Socket start &
# Socket-停止
elif [ "$s" == "socketStop" ]; then
  ps -aux | grep "$cli Socket start" | grep -v grep | awk {'print $2'} | xargs kill
else
  echo "----------------------------------------------------"
  echo "[use] ./bash <command>"
  echo "----------------------------------------------------"
  echo "<command>"
  echo "  serve         运行: php -S $ip:$port -t public"
  echo "  install       依赖包: composer install"
  echo "<WebSocket>"
  echo "  socket        运行"
  echo "  socketStart   启动"
  echo "  socketStop    停止"
  echo "----------------------------------------------------"
fi
