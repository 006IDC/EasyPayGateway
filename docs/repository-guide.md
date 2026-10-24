# V10 易支付 V1 网关插件 · 使用说明

适用于 ZJMF-CBAP V10 的独立支付网关，通过可配置的 HTTPS 收银台地址接入易支付 V1。

[产品首页](../README.md) · [部署说明](DEPLOYMENT.md) · [配置手册](CONFIGURATION.md) · [版本下载](https://github.com/006IDC/v10-easypay-gateway-public/releases) · [更新记录](../CHANGELOG.md)

## 使用前确认

- 当前公开发行：`1.0.1`；按 V10 `10.4.6` 本地源码合同开发，目标实例仍需独立验证。
- PHP 保持 7.3 语法兼容；MySQL 使用 InnoDB，并支持 `GET_LOCK()` / `RELEASE_LOCK()`。
- 仅支持 CNY；插件不声明自动退款能力。
- 需要自己的易支付 V1 HTTPS 提交地址、数字商户 ID、商户密钥及 V10 站点 HTTPS 地址。仓库不提供真实商户或生产配置。

## 安装与配置

1. 下载 `easy_pay_gateway_1.0.1.zip`，解压至 V10 `public/plugins/`，不是在后台上传 ZIP。
2. 确认入口为 `public/plugins/gateway/easy_pay_gateway/EasyPayGateway.php`，在后台安装插件。
3. 按[配置手册](CONFIGURATION.md)填写服务商提交地址、商户信息、支付方式与回调站点地址，再启用插件。
4. 按[部署说明](DEPLOYMENT.md)登记通知与返回地址，并完成支付发起、异步入账和重复通知验证。

GitHub 自动生成的 Source code ZIP 不是安装包。`https://pay.example.com/submit.php` 和 `https://client.example.com` 仅为占位地址，不能用于真实收款。

## 支付流程

1. 用户在 V10 订单中选择网关，插件生成 HTTPS POST 支付表单。
2. 支持 redirect 合同的主题可自动提交 `form[data-v10-payment-mode="redirect"]`；旧主题使用保留的手动按钮。插件自身不注入自动提交 JavaScript。
3. 收银台支付后，同步返回仅跳回订单页，不直接入账。
4. 异步通知通过完整校验后，插件使用 V10 `order_pay_handle()` 入账；重复通知须保持幂等。

支持聚合收银台，以及 `alipay`、`wxpay`、`qqpay`、`bank`、`jdpay`、`paypal`、`douyinpay` 固定通道；实际可用通道以服务商配置为准。

## 安全与数据规则

- 发起时保存金额、商户、正式订单与通道快照；入账前复核签名、商户、`TRADE_SUCCESS` 成功终态、金额、通道、临时订单及正式订单关系。
- 按订单与平台流水获取 MySQL 命名锁；金额使用十进制字符串处理，不以 PHP 浮点进行最终财务比较。
- 收银台提交地址与回调站点地址均须使用 HTTPS。易支付 V1 的 MD5 拼接密钥仅是历史兼容算法，不视为现代 MAC。
- `install()` 与 `upgrade()` 使用幂等迁移；卸载默认保留支付意图表供财务核对。正常升级不使用卸载重装。
- 密钥只在受保护配置中填写，不进入源码、浏览器地址栏或公开日志。

风险与检查范围见[安全策略](../SECURITY.md)和[安全审计](SECURITY_AUDIT.md)。

## 开发与验证

稳定身份为 `gateway / EasyPayGateway / easy_pay_gateway`。不要更改目录名、入口文件、入口类或 `$info.name`；后续通过语义版本升级。

```text
plugins/gateway/easy_pay_gateway/
├── EasyPayGateway.php
├── EasyPayGateway.png
├── config.php
├── controller/IndexController.php
├── lib/EasyPaySigner.php
├── lib/Money.php
└── service/EasyPayService.php
```

源码目录中的 `plugins/` 可合并到 V10 `public/plugins/`，具体放置规则见[部署说明](DEPLOYMENT.md)。在源码仓库根目录执行检查与构建：

```bash
sh scripts/check.sh
sh scripts/build-release.sh
```

构建产物为 `easy_pay_gateway_1.0.1.zip`。代码检查不替代目标实例的支付验证。

## 许可与维护

采用 [MIT License](../LICENSE)，第三方边界见[第三方声明](../THIRD_PARTY_NOTICES.md)。由[零零陆云计算（006IDC）](https://github.com/006IDC)独立维护。
