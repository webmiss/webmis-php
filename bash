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
# Socket-运行(查看)
elif [ "$s" == "socketShow" ]; then
  ps -aux | grep "$cli Socket" | grep -v grep
# Socket-运行(服务器)
elif [ "$s" == "socketServer" ]; then
  php $cli Socket server
# Socket-运行(客户端)
elif [ "$s" == "socketClient" ]; then
  php $cli Socket client admin
# Socket-启动(服务器)
elif [ "$s" == "socketServerStart" ]; then
  nohup php $cli Socket server &
# Socket-启动(客户端)
elif [ "$s" == "socketClientStart" ]; then
  nohup php $cli Socket client admin &
# Socket-(服务器)
elif [ "$s" == "socketServerStop" ]; then
  ps -aux | grep "$cli Socket server" | grep -v grep | awk {'print $2'} | xargs kill
# Socket-(客户端)
elif [ "$s" == "socketClientStop" ]; then
  ps -aux | grep "$cli Socket client" | grep -v grep | awk {'print $2'} | xargs kill
else
  echo "----------------------------------------------------"
  echo "[use] ./bash <command>"
  echo "----------------------------------------------------"
  echo "<command>"
  echo "  serve               运行: php -S $ip:$port -t public"
  echo "  install             依赖包: composer install"
  echo "<WebSocket>"
  echo "  socketShow          查看"
  echo "  socketServer        运行(服务器)"
  echo "  socketServerStart   启动(服务器)"
  echo "  socketServerStop    停止(服务器)"
  echo "  socketClient        运行(客户端)"
  echo "  socketClientStart   启动(客户端)"
  echo "  socketClientStop    停止(客户端)"
  echo "----------------------------------------------------"
fi
