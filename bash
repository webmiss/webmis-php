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
# Socket服务器-查看、运行、启动、停止
elif [ "$s" == "socketShow" ]; then
  ps -aux | grep "$cli Socket" | grep -v grep
elif [ "$s" == "socketServer" ]; then
  php $cli Socket server
elif [ "$s" == "socketServerStart" ]; then
  nohup php $cli Socket server &
elif [ "$s" == "socketServerStop" ]; then
  ps -aux | grep "$cli Socket server" | grep -v grep | awk {'print $2'} | xargs kill
# Socket客户端-运行、启动、停止
elif [ "$s" == "socketClient" ]; then
  php $cli Socket client admin
elif [ "$s" == "socketClientStart" ]; then
  nohup php $cli Socket client admin &
elif [ "$s" == "socketClientStop" ]; then
  ps -aux | grep "$cli Socket client" | grep -v grep | awk {'print $2'} | xargs kill
# Logs-查看、运行、启动、停止
elif [ "$s" == "LogsShow" ]; then
  ps -aux | grep "$cli Logs" | grep -v grep
elif [ "$s" == "Goods" ]; then
  php $cli Logs Goods
elif [ "$s" == "GoodsStart" ]; then
  nohup php $cli Logs Goods 2>&1 &
elif [ "$s" == "GoodsStop" ]; then
  ps -aux | grep "$cli Logs Goods" | grep -v grep | awk {'print $2'} | xargs kill
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
  echo "<Logs>"
  echo "  LogsShow            查看"
  echo "  Goods               运行"
  echo "  GoodsStart          启动"
  echo "  GoodsStop           停止"
  echo "----------------------------------------------------"
fi
