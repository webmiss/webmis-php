# webmis-php
采用PHP8 + Redis开发的轻量级HMVC基础框架，目录结构清晰，支持CLI方式访问资料方便执行定时脚本。包括HMVC模块化管理、自动路由、CLI命令行、Socket通信、redis缓存、Token机制等功能，提供支付宝、微信、文件上传、图像处理、二维码等常用类。[使用文档](https://webmis.vip/php/install/index)
**演示**
- 网站-API( [https://php.webmis.vip/](https://php.webmis.vip/) )
- 前端-API( [https://php.webmis.vip/api/](https://php.webmis.vip/api/) )
- 后台-API( [https://php.webmis.vip/admin/](https://php.webmis.vip/admin/) )

## 安装
```bash
$ git clone https://github.com/webmiss/webmis-php.git
$ cd webmis-php
$ composer install
```

## 运行
```bash
# Linux、MacOS
./bash serve
# Windows
.\cmd serve
```
- 浏览器访问 http://127.0.0.1:9000/
- 打印信息到终端: self::Print('内容');

## 命令行
```bash
# 控制器->方法(参数...)
php cli.php main index params
```
- 浏览器访问 http://127.0.0.1:9000/

