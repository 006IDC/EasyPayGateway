# V10 易支付 V1 网关插件：运行与维护说明

[返回产品首页](../README.md)。以下完整保留整理前的公开 README 技术内容与来源、版权及许可证声明；仅调整移入 `docs/` 后的仓内链接。内容对应原公开说明，不新增测试或部署结论。

---

# EasyPay Gateway for ZJMF-CBAP V10

[![CI](https://github.com/006IDC/EasyPayGateway/actions/workflows/ci.yml/badge.svg)](https://github.com/006IDC/EasyPayGateway/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/006IDC/EasyPayGateway)](https://github.com/006IDC/EasyPayGateway/releases)
[![License](https://img.shields.io/github/license/006IDC/EasyPayGateway)](../LICENSE)

由 [零零陆云计算（006IDC）](https://github.com/006IDC) 独立维护。

一个可自行配置易支付 V1 收银台地址的 ZJMF-CBAP V10 Gateway 插件。仓库不包含真实商户号、商户密钥、生产域名、服务器资料或客户数据。

## 主要能力

- 按易支付 V1 规则生成和校验 MD5 兼容签名。
- 使用可配置的 HTTPS POST 收银台地址发起支付。
- 支持聚合收银台及 `alipay`、`wxpay`、`qqpay`、`bank`、`jdpay`、`paypal`、`douyinpay` 固定通道。
- 输出唯一的 `form[data-v10-payment-mode="redirect"]`，可由通用 V10 主题自动提交。
- 不在插件内注入自动提交 JavaScript，保留手动提交按钮兼容旧主题。
- 保存发起金额、商户、正式订单和通道快照。
- 回调入账前复核签名、商户、成功终态、金额、通道、临时订单和正式订单关系。
- 按订单与平台流水获取 MySQL 命名锁，并复用 V10 `order_pay_handle()` 入账。
- `install()` 和 `upgrade()` 共用幂等迁移；卸载默认保留支付意图表用于财务核对。

## 公开版身份

```text
module: gateway
name: EasyPayGateway
slug: easy_pay_gateway
version: 1.0.1
```

这是公开版的新稳定身份。首次发布后不要修改目录名、入口文件、入口类或 `$info.name`，后续只通过语义版本号升级。

## 目录结构

```text
plugins/
└── gateway/
    └── easy_pay_gateway/
        ├── controller/IndexController.php
        ├── lib/EasyPaySigner.php
        ├── lib/Money.php
        ├── service/EasyPayService.php
        ├── EasyPayGateway.php
        ├── EasyPayGateway.png
        └── config.php
```

仓库的 `plugins/` 目录可直接与 V10 `public/plugins/` 合并。详细安装步骤见 [部署说明](../docs/DEPLOYMENT.md)。

## 重要配置

仓库中的域名全部是文档保留域名：

```text
收银台提交地址: https://pay.example.com/submit.php
V10 回调站点地址: https://client.example.com
```

`example.com` 不用于真实收款。安装后必须在 V10 后台填写你自己的易支付服务商 HTTPS 提交地址、商户 ID、商户密钥和当前 V10 站点 HTTPS 地址。

完整配置说明见 [配置手册](../docs/CONFIGURATION.md)。

## 环境基线

- ZJMF-CBAP V10：按 `10.4.6` 本地源码合同开发，目标版本仍应独立验证。
- PHP：保持 PHP 7.3 语法兼容。
- MySQL：InnoDB，需支持 `GET_LOCK()` / `RELEASE_LOCK()`。
- 币种：仅 CNY。
- 主题：支持 redirect 能力合同的主题可自动提交；其他主题可使用保留的按钮。

## 测试与打包

```bash
sh scripts/check.sh
sh scripts/build-release.sh
```

构建产物：

- `easy_pay_gateway_1.0.1.zip`：上传到 V10 的 `public/plugins/` 目录并解压。

解压后的入口文件必须位于 `public/plugins/gateway/easy_pay_gateway/EasyPayGateway.php`，随后在后台安装、配置并启用插件。GitHub 自动生成的 Source code ZIP 不是插件安装包。

## 支付安全边界

- 同步返回仅跳回 V10 订单页，不入账。
- 只有验签成功的异步 `TRADE_SUCCESS` 通知才可调用核心入账。
- 金额使用十进制字符串处理，不用 PHP 浮点完成最终财务比较。
- 收银台提交地址和回调站点地址都强制使用 HTTPS。
- 易支付 V1 使用的 MD5 拼接密钥是历史兼容算法，不应被视为现代 MAC。
- 插件不声明自动退款能力。

威胁模型和剩余风险见 [安全说明](../SECURITY.md) 和 [安全审计](../docs/SECURITY_AUDIT.md)。

## 许可

项目使用 [MIT License](../LICENSE)。第三方软件边界见 [THIRD_PARTY_NOTICES.md](../THIRD_PARTY_NOTICES.md)。
