@echo off
CHCP 65001 >nul

REM 配置
set s=%1%
set ip=127.0.0.1
set port=9000
set cli=cli.php

REM 运行
 if "%s%"=="serve" (
  ( php -S %ip%:%port% -t public ) || ( echo ^> 请安装'php' )
REM 安装
) else if "%s%"=="install" (
  ( del composer.lock 2>nul && composer install ) || ( echo ^> 请安装'composer' )
REM Socket-运行(服务器)
) else if "%s%"=="socketServer" (
  php %cli% Socket server
REM Socket-运行(客户端)
) else if "%s%"=="socketClient" (
  php %cli% Socket client admin '{"type":"","msg":"\u6d4b\u8bd5"}'
) else (
  echo ----------------------------------------------------
  echo [use] cmd.bat ^<command^>
  echo ----------------------------------------------------
  echo ^<command^>
  echo   serve              运行: php -S %ip%:%port% -t public
  echo   install            依赖包: composer install
  echo ^<WebSocket^>
  echo   socketServer       运行(服务器)
  echo   socketClient       发送(客户端)
  echo ----------------------------------------------------
)