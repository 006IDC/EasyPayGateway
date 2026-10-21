# V10 易支付 V1 网关插件

## 产品介绍

适用于 ZJMF-CBAP V10 的易支付 V1 聚合支付网关，支持自行配置 HTTPS 收银台地址。

## 版本概览

| 项目 | 公开说明 |
| --- | --- |
| 公开发行版本 | [v1.0.1](https://github.com/006IDC/EasyPayGateway/releases/tag/v1.0.1) |
| 适用环境 | 按 V10 10.4.6 本地源码合同开发；PHP 7.3 语法兼容 / MySQL InnoDB |
| 使用边界 | 目标实例仍需独立验证；仅 CNY，不声明自动退款能力。V1 的 MD5 拼接密钥属于历史兼容算法。 |

版本以本仓公开 Release 为准；完整技术说明见[运行与维护说明](docs/repository-guide.md)。

## 主要功能

- 易支付 V1 签名、HTTPS POST 提交与聚合或固定通道。
- 支付金额、商户、订单和通道快照及异步通知校验。
- MySQL 命名锁与 V10 原生 order_pay_handle() 入账；同步返回不入账。

## 使用与升级

公开身份保持 `gateway / EasyPayGateway / easy_pay_gateway`。下载发行包 `easy_pay_gateway_1.0.1.zip`，按[部署说明](docs/DEPLOYMENT.md)解压至 V10 `public/plugins/`，再后台安装、配置与启用。GitHub 自动源码 ZIP 不是安装包；示例域名不能用于真实收款。详细配置和升级边界见[技术说明](docs/repository-guide.md)。

## 版本演进

版本变化见[变更记录](CHANGELOG.md)。适用环境和部署检查以对应版本文档为准。

## 许可证与维护

由[零零陆云计算（006IDC）](https://github.com/006IDC)独立维护。采用 [MIT License](LICENSE)，第三方边界见[第三方声明](THIRD_PARTY_NOTICES.md)，风险说明见[安全策略](SECURITY.md)。
