# Changelog

## 2026-10-22 · Actions 依赖维护

- 将 PHP 环境 Action 更新到 2.40.0，将 SBOM Action 更新到 0.24.3，继续固定完整提交号。
- 在当前主线上整合原依赖 PR #5、#4，保留原提交与业务源码。沿用原有 CI 验证语法、合同及安装包；不创建版本标签或发行。

## 1.0.1 - 2026-08-12

- 发布通用易支付 V1 聚合支付网关。
- 使用稳定身份 `gateway / EasyPayGateway / easy_pay_gateway`。
- 支持后台配置 HTTPS 收银台提交地址。
- 支持 V10 redirect 支付能力标记。
- 加入金额快照、订单归属复核、并发锁和重复通知幂等。
- 提供一个可直接解压到 V10 `public/plugins/` 的安装包。
- 加入幂等安装/升级迁移、合同测试、GitHub Actions 和发布前脱敏检查。
