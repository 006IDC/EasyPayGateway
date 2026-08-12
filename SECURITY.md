# Security Policy

## Supported version

| Version | Supported |
|---|---|
| 1.0.x | Yes |

## Reporting a vulnerability

请使用仓库 Security 页面的私密漏洞报告功能。不要在公开 Issue 中提交尚未修复的漏洞。

报告建议包含：

- 受影响的插件、V10、PHP 和 MySQL 版本。
- 影响、触发条件和最小复现步骤。
- 已脱敏的请求、回调或日志摘要。
- 可能的缓解或修复建议。

不得提交商户密钥、真实支付者信息、会话、生产数据库、服务器凭据或未脱敏日志。

## Credential incident

如果怀疑商户密钥泄漏，应立即在支付服务商处轮换密钥，更新 V10 后台配置，复核收款和回调记录，验证新密钥后再恢复网关。
