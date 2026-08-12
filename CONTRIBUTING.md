# Contributing

## Development rules

- 保持 `gateway / EasyPayGateway / easy_pay_gateway` 稳定身份。
- 不修改 V10 Core 来完成 Gateway 功能。
- 保持 PHP 7.3 语法兼容。
- 金额使用十进制字符串，不使用浮点数完成最终财务比较。
- 支付回调修改必须保留签名、商户、金额、订单归属、终态、流水、并发与幂等校验。
- `upgrade()` 必须可重复执行并安全成功。
- 不在代码、测试、文档、Issue 或截图中提交真实凭据和客户数据。
- 文档和测试中的域名使用 `example.com` 及其子域。

## Pull request checklist

1. 运行 `sh scripts/check.sh`。
2. 为新行为增加正向和反向合同测试。
3. 更新 README、CHANGELOG 和必要的安全/部署文档。
4. 运行 `sh scripts/build-release.sh`，核对安装包的文件清单和 SHA-256。
5. 在干净的目标 V10 环境中完成首次安装、两次幂等 `upgrade()` 和最小金额端到端验收。
