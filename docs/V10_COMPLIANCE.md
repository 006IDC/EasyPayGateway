# V10 开发准则符合性

本清单依据工作区 `V10_DEVELOPMENT_GUIDE.md` 与本地 V10 10.4.6 源码合同整理。仓库不包含该内部开发指南或 V10 Core 源码。

| 检查项 | 结果 | 证据 |
| --- | --- | --- |
| Gateway 目录与命名空间一致 | 通过 | `plugins/gateway/easy_pay_gateway` 与 `gateway\easy_pay_gateway` 一致。 |
| 入口身份稳定 | 通过 | `$info.name`、入口类和处理方法统一为 `EasyPayGateway`。 |
| 订单号规则声明 | 通过 | `$orderRule = 2`，生成 18 位纯数字临时订单号。 |
| 配置采用 V10 支持的 schema | 通过 | `config.php` 使用 text、password、select、rule 和 message。 |
| 安装与升级幂等 | 通过 | `install()` 和 `upgrade()` 共用 `CREATE TABLE IF NOT EXISTS` 迁移。 |
| 卸载保护财务证据 | 通过 | `uninstall()` 不删除支付意图表。 |
| 不修改 Core | 通过 | 运行包只包含 Gateway 插件目录的 7 个文件。 |
| 金额不用浮点判断 | 通过 | `Money` 使用十进制字符串规范化和比较。 |
| 支付发起核对订单归属 | 通过 | 发起前同时检查 finance 临时订单与核心临时订单、正式订单关系。 |
| 回调先验签再入账 | 通过 | 签名、商户、状态、金额、通道、意图和订单关系均在 `order_pay_handle()` 前核对。 |
| 同步返回不入账 | 通过 | `returnHandle()` 只验签并返回核心订单跳转地址。 |
| 异步通知输出纯文本 | 通过 | `notifyHandle()` 返回 `success` 或 `fail`。 |
| redirect 支付能力合同 | 通过 | 唯一表单包含 `data-v10-payment-mode="redirect"`、POST、HTTPS action 和非 disabled hidden input。 |
| 插件不自动提交 | 通过 | 运行代码无脚本标签，保留手动提交按钮。 |
| 不做主题或客户端分支 | 通过 | 输出与主题名、客户端类型及 User-Agent 无关。 |
| 并发和重复通知控制 | 通过 | 唯一索引、订单锁、流水锁和已有交易核对。 |
| 直接 plugins 目录安装包 | 通过 | 构建脚本生成根目录为 `gateway/easy_pay_gateway/` 的部署包。 |
| 运行包文件白名单 | 通过 | 安装包只包含 7 个运行文件。 |
| 公开仓库资料 | 通过 | 包含 README、部署、配置、架构、安全、贡献、许可、变更和发布说明。 |
| 私密信息隔离 | 通过 | 自动扫描禁止私有标识、凭据模式、个人路径和非示例 URL。 |

## 仍需部署者验证

- 目标 V10 版本与本地 10.4.6 合同是否一致。
- 目标服务器的 PHP、MySQL、反向代理和 HTTPS 配置。
- 服务商的具体易支付 V1 字段、编码、通道和回调行为。
- 真实主题是否实现 redirect 公共支付合同。
- 真实支付的成功、失败、超时、重复通知和人工对账流程。

这些项目依赖外部环境，不能由纯本地合同测试替代。
