# onebot-woap

![](https://img.shields.io/badge/OneBot-12-black)
![](https://img.shields.io/github/license/zhamao-robot/zhamao-framework?label=License)

微信公众平台（订阅号）聊天的机器人 OneBot 12 实现。

Woap 全称是 WeChat Official Accounts Platform。

首次采用 [php-libonebot](https://github.com/botuniverse/php-libonebot) 快速开发！

## 提示

本项目还属于**最初期**阶段，暂不支持所有功能，如有需要，请参考未来的更新日志，且功能目前仅为可用性，不保证完全符合 OneBot V12 的要求。

## 用法

从 Release 中下载二进制 `woap、woap.exe` 或下载 Phar 包是哟本机 PHP 环境运行即可。

二进制版本无需 PHP 环境，为纯静态编译的版本，支持 Linux、MacOS、Windows 等多种操作系统。

Phar 版本可以灵活调整 PHP 环境，可使用本机 PHP 运行。

## 支持状况

- [X] 支持微信公众号 Webhook 接入
- [X] 支持微信公众订阅号消息的被动回复
- [X] 支持不同通信方式的扩展
- [ ] 支持文件上传接口
- [X] 支持收取图片
