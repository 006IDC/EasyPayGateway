# 发布说明

## 稳定身份

公开版固定使用：

```text
module: gateway
name: EasyPayGateway
slug: easy_pay_gateway
entry: EasyPayGateway.php
class: EasyPayGateway
```

后续发布不得通过改名生成新插件，也不得要求用户卸载后重新安装。数据库变化必须放在幂等 `upgrade()` 中，并验证从上一公开版本原地升级。

## 发布前清单

1. 更新入口文件版本和 `CHANGELOG.md`。
2. 确认 `install()`、`upgrade()` 与历史数据兼容。
3. 运行 `sh scripts/check.sh`。
4. 运行 `sh scripts/build-release.sh`。
5. 检查 `dist/SHA256SUMS` 和安装包文件清单。
6. 在干净 V10 测试环境把安装包上传到 `public/plugins/` 解压，验证后台能自动识别。
7. 在上一公开版本副本验证覆盖和后台升级，无需卸载。
8. 验证支付发起、合法回调、重复回调、非法签名、错误金额、同步返回和回滚。
9. 确认发布包与 Git tag 使用相同语义版本。
10. 发布 GitHub Release，并附上唯一安装包、SHA-256 和变更说明。

## 构建产物

`scripts/build-release.sh` 生成：

```text
dist/easy_pay_gateway_VERSION.zip
dist/SHA256SUMS
```

安装包根目录为 `gateway/easy_pay_gateway/`。把 ZIP 上传到 V10 的 `public/plugins/` 并在该目录解压，后台即可识别插件。GitHub 自动生成的源码压缩包不是插件安装包。

构建脚本把文件时间规范化并移除 ZIP 扩展属性，以便在相同工具链和源文件下复现运行包。

## 发布边界

不得加入：

- 真实商户 ID、商户密钥或测试密钥；
- 生产域名、IP 地址、服务器账号或密码；
- 客户数据、日志、数据库备份或支付通知样本；
- V10 Core 源码或商业资源；
- 编辑器配置、证书、私钥、签名密钥或内部发布系统文件。

文档和测试中的域名统一使用 `example.com` 及其子域名，其他必填值使用明显不可用于生产的示例值。
