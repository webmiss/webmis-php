@echo off
chcp 65001 >nul 2>&1

REM 配置
set s=%1%
set ip=127.0.0.1
set port=9000
set cli=cli.php
set php_dir=D:\soft\php
set php_url=https://windows.php.net/downloads/releases/php-8.3.29-nts-Win32-vs16-x64.zip
set composer_url=https://install.phpcomposer.com/installer
set composer_mirrors=https://repo.packagist.org
set php_ext=fileinfo,gd,mbstring,openssl,pdo_mysql

@REM PHP环境
php -v >nul 2>&1
if %errorlevel% neq 0 (
  @REM 是否存在目录
  if not exist "%php_dir%\" (
    md "%php_dir%" >nul 2>&1
    if exist "%php_dir%\" (
      echo [✓] 目录创建成功：%php_dir%
    ) else (
      echo [✗] 目录创建失败: %php_dir%
    )
  )
  @REM 下载文件
  if not exist "%php_dir%\php.exe" (
    if not exist "php.zip" (
      echo [✓] 下载文件：%php_url%
      curl -L "%php_url%" -o php.zip
    )
    @REM 解压文件
    powershell -Command "Expand-Archive -Path 'php.zip' -DestinationPath '%php_dir%' -Force"
    echo [✓] 解压文件：php.zip 到 %php_dir%
    @REM 删除文件
    del php.zip
  )
  @REM 环境变量
  set PATH=%PATH%;%php_dir%
  echo [✓] 环境变量：%php_dir%
)

@REM 运行
if "%s%"=="serve" (
  php -S %ip%:%port% -t public
@REM 安装
) else if "%s%"=="install" (
  @REM 配置文件
  if not exist "%php_dir%\php.ini" (
    copy /Y %php_dir%\php.ini-development %php_dir%\php.ini
    echo [✓] 配置文件: %php_dir%\php.ini
  )
  @REM 修改扩展
  echo [✓] 添加扩展: %php_dir%\php.ini
  powershell -Command "(gc '%php_dir%\php.ini') -replace ';extension_dir = \"ext\"','extension_dir = \"%php_dir%\ext\"' | sc '%php_dir%\php.ini'"
  echo extension_dir = "%php_dir%\ext"
  for /f "delims=, tokens=*" %%i in ("%php_ext%") do (
    for %%e in (%%i) do (
      powershell -Command "(gc '%php_dir%\php.ini') -replace ';extension=%%e','extension=%%e' | sc '%php_dir%\php.ini'"
      echo extension=%%e
    )
  )
  @REM Composer环境
  if not exist "%php_dir%\composer.phar" (
    @REM 下载Composer
    echo [✓] 下载Composer：%composer_url%
    curl -L "%composer_url%" -o composer-setup.php
    @REM 安装Composer
    php composer-setup.php
    move /Y composer.phar %php_dir%
    echo [✓] 已安装Composer：%composer_url%
    @REM 删除文件
    del composer-setup.php
  )
  @REM 镜像源
  php %php_dir%\composer.phar config -g repo.packagist composer %composer_mirrors%
  @REM 版本信息
  php %php_dir%\composer.phar -V
  @REM 安装依赖
  del composer.lock
  php %php_dir%\composer.phar install
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
  echo ----------------------------------------------------
)