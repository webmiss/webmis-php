# webmis-php
采用PHP8 + Redis + MariaDB开发的轻量级HMVC基础框架，目录结构清晰，支持CLI方式访问资料方便执行定时脚本。包括HMVC模块化管理、自动路由、CLI命令行、Socket通信、redis缓存、Token机制等功能，提供支付宝、微信、文件上传、图像处理、二维码等常用类。

**演示**
- 使用文档( [https://webmis.vip/](https://webmis.vip/php/install/index) )
- 网站-API( [https://php.webmis.vip/](https://php.webmis.vip/) )
- 前端-API( [https://php.webmis.vip/api/](https://php.webmis.vip/api/) )
- 后台-API( [https://php.webmis.vip/admin/](https://php.webmis.vip/admin/) )

## 安装
```bash
# 下载
git clone https://github.com/webmiss/webmis-php.git
cd webmis-php
# Linux、MacOS
./bash install
# Windows
.\cmd install
```

## 开发环境
```bash
# Linux、MacOS
./bash serve
./bash socketServer
# Windows
.\cmd serve
.\cmd socketServer
# 测试Socket
php cli.php socket client admin '{"type":"","msg":"\u6d4b\u8bd5"}'
# 命令行: 控制器->方法(参数...)
php cli.php main index params
```
- 浏览器访问 http://127.0.0.1:9000/
- 测试Socket ws://127.0.0.1:9001/?channel=admin&token=Token

## 生产环境
*** Ubuntu ***
```bash
# Nginx
apt install nginx -y
apt autoremove -y
# MariaDB
apt install mariadb-server -y
# Redis
apt install redis-server -y
# PHP
apt install php-fpm php-cli php-mysql php-gd php-xml php-mbstring -y
```

*** Nginx ***
```bash
server {
    server_name  php.webmis.vip;
    set $root_path /home/www/webmis/php/public;
    root $root_path;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?_url=$uri&$args;
    }
    location ~* ^/(upload|favicon.png)/(.+)$ {
        root $root_path;
        add_header 'Access-Control-Allow-Origin' '*';
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS';
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization';
        if ($request_method = 'OPTIONS') { return 204; }
    }

    location ~ \.php$ {
        fastcgi_pass   unix:/run/php/php8.3-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }
}
```

## 项目结构
```plaintext
webmis-php/
├── app
│    ├── config                 // 配置文件
│    ├── librarys               // 第三方类
│    ├── models                 // 模型
│    └── modules                // 模块
│    │    ├── admin            // 后台
│    │    ├── api              // 应用
│    │    └── home             // 网站
│    ├── service                // 项目服务类
│    ├── task                   // 任务类
│    ├── util                   // 工具类
│    └── views                  // 视图文件
├── core
│    ├── Base.php               // 基础类
│    ├── Controller.php         // 基础控制器
│    ├── Model.php              // 基础模型
│    ├── Redis.php              // 缓存数据库
│    ├── Router.php             // HMVC 路由
│    ├── RouterCli.php          // Cli 路由
│    └── View.php               // 基础视图
├── public                       // 静态资源
│    ├── upload                 // 上传目录
│    └── index.php              // 人口文件
├── bash                         // Linux/MacOS 启动脚本
├── cmd.bat                      // Windows 启动脚本
├── cli.php                      // 命令行: php cli.php 控制器 函数 参数...
└── composer.json                // Composer 配置文件
```
