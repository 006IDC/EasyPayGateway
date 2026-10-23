# V10 易支付 V1 网关插件（开源版）

适用于 ZJMF-CBAP V10 的易支付 V1 聚合支付网关，支持自行配置 HTTPS 收银台地址。

[使用文档](docs/repository-guide.md) · [部署说明](docs/DEPLOYMENT.md) · [版本下载](https://github.com/006IDC/v10-easypay-gateway-public/releases) · [更新记录](CHANGELOG.md)

**当前发行：** [v1.0.1](https://github.com/006IDC/v10-easypay-gateway-public/releases/tag/v1.0.1)

## 主要功能

- 易支付 V1 签名、HTTPS POST 提交，支持聚合或固定通道。
- 支付金额、商户、订单和通道快照，以及异步通知校验。
- MySQL 命名锁与 V10 原生 `order_pay_handle()` 入账；同步返回不入账。

## 安装与升级

目标基线：V10 10.4.6；PHP 7.3 语法兼容，数据库使用 MySQL InnoDB。

1. 从 [Releases](https://github.com/006IDC/v10-easypay-gateway-public/releases) 下载 `easy_pay_gateway_1.0.1.zip`，按[部署说明](docs/DEPLOYMENT.md)解压至 V10 `public/plugins/`。
2. 在后台安装、配置并启用。插件身份保持 `gateway / EasyPayGateway / easy_pay_gateway`；配置与升级要求见[使用文档](docs/repository-guide.md)。

> GitHub 自动源码 ZIP 不是安装包；示例域名不能用于真实收款。

## 版本与兼容性

- 按 V10 10.4.6 本地源码合同开发，目标实例仍需独立验证。
- 仅支持 CNY，不声明自动退款能力；V1 的 MD5 拼接密钥属于历史兼容算法。
- 2026-10-22 维护了 GitHub Actions 依赖，仅更新工作流与说明，保留当前公开发行版本。完整变化见[更新记录](CHANGELOG.md)。

## 许可证与维护

由[零零陆云计算（006IDC）](https://github.com/006IDC)独立维护。采用 [MIT License](LICENSE)；第三方边界见[第三方声明](THIRD_PARTY_NOTICES.md)，风险说明见[安全策略](SECURITY.md)。
