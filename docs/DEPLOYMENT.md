# V10 易支付 V1 网关插件 · 部署说明

适用于公开版 `1.0.1`。按准备、安装、配置、验证、升级与回退的顺序操作。

[产品首页](../README.md) · [使用说明](repository-guide.md) · [配置手册](CONFIGURATION.md) · [版本下载](https://github.com/006IDC/v10-easypay-gateway-public/releases)

## 1. 部署前检查

目标基线为 V10 10.4.6，其他目标实例仍需独立验证。以下示例域名均须替换，不能用于真实收款。

- 已备份 V10 数据库和 `public/plugins/`。
- V10 运行环境使用 PHP 7.3 或兼容版本。
- MySQL 表使用 InnoDB，并允许当前数据库账户调用 `GET_LOCK()` 和 `RELEASE_LOCK()`。
- V10 系统币种为 CNY。
- 已从支付服务商取得易支付 V1 的 HTTPS 提交地址、数字商户 ID 和商户密钥。
- V10 站点有可公网访问的 HTTPS 地址，例如 `https://client.example.com`。

## 2. 获取并安装

### 推荐：发行安装包

从[版本下载](https://github.com/006IDC/v10-easypay-gateway-public/releases)选择对应发行包。

下载发布页中的：

```text
easy_pay_gateway_1.0.1.zip
```

把 ZIP 上传到 V10 服务器的：

```text
public/plugins/
```

在该目录解压。压缩包根目录是 `gateway/easy_pay_gateway/`，解压后的入口文件必须位于：

```text
public/plugins/gateway/easy_pay_gateway/EasyPayGateway.php
```

回到 V10 后台插件页面，系统会自动识别“易支付聚合网关”。不需要在后台上传 ZIP，也不需要执行额外的导入步骤。

### 开发者：源码目录

开发者也可以把仓库中的：

```text
plugins/gateway/easy_pay_gateway
```

复制到 V10 的：

```text
public/plugins/gateway/easy_pay_gateway
```

不要把仓库的 `README.md`、`docs/`、`tests/`、`scripts/` 或 GitHub 自动生成的 Source code ZIP 解压进 V10 运行目录，也不要覆盖任何 Core 文件。

## 3. 配置与启用

1. 解压安装包后刷新后台插件页面，找到名称为“易支付聚合网关”的 Gateway 插件。
2. 安装插件。`install()` 会幂等创建支付意图表。
3. 按[配置手册](CONFIGURATION.md)填写收银台提交地址、商户 ID、商户密钥、支付方式和回调站点地址。
4. 保存配置后启用插件。
5. 在支付服务商后台登记以下地址：

```text
异步通知: https://client.example.com/gateway/easy_pay_gateway/index/notifyHandle
同步返回: https://client.example.com/gateway/easy_pay_gateway/index/returnHandle
```

其中 `https://client.example.com` 必须替换为当前 V10 站点的真实 HTTPS 地址。

## 4. 验证部署

优先使用服务商测试商户；真实小额支付会产生实际交易，需部署方确认后执行。

逐项核对：

1. 创建充值订单后，支持 redirect 合同的主题应自动 POST 到收银台。
2. 未支持该合同的主题应显示“前往收银台”按钮，点击后仍以 POST 提交。
3. 浏览器地址栏不应出现商户密钥或完整签名参数拼接。
4. 同步返回只负责跳回订单页，不应直接把订单改为已支付。
5. 合法异步成功通知应只入账一次；重复通知应保持幂等。
6. V10 交易流水中的网关名称应为 `EasyPayGateway`。

仅支持 CNY，插件不提供自动退款能力；V1 的 MD5 拼接密钥属于历史兼容算法。完整边界见[使用说明](repository-guide.md)和[安全策略](../SECURITY.md)。

## 5. 升级与回退

### 正常升级

公开版稳定身份为 `gateway / EasyPayGateway / easy_pay_gateway`。后续版本必须保持目录、入口类、入口文件和 `$info.name` 不变，并通过幂等 `upgrade()` 完成升级。

1. 先备份数据库和旧插件目录。
2. 解压新版本并覆盖同名插件目录。
3. 在 V10 后台执行升级。
4. 重新运行支付发起、异步通知和重复通知测试。

不要通过卸载再安装来代替正常升级。

### 异常回退

如果启用后出现异常：

1. 先在后台停用插件，阻止新订单选择该网关。
2. 恢复已备份的插件目录。
3. 如升级包含数据库迁移，按对应版本的变更说明回滚；不要直接删除财务核对数据。

插件的 `uninstall()` 默认保留 `gateway_easy_pay_gateway_intent` 对应的前缀表，便于核对历史支付。确认审计与留存要求后，才可由数据库管理员另行处理。

## 6. 常见问题

| 现象 | 先检查 |
| --- | --- |
| 后台不能识别插件 | 入口文件是否在 `public/plugins/gateway/easy_pay_gateway/`，是否多套目录 |
| 收银台不能跳转 | 提交地址与通道配置、主题的 redirect 合同；旧主题使用手动按钮 |
| 支付后未入账 | 服务商异步通知可达性与日志；同步返回本来不负责入账，不可手工绕过验签 |
| 重复通知或核账异常 | 保留订单、平台流水与意图表记录，按[安全审计](SECURITY_AUDIT.md)核对，不删除财务数据 |

配置、日志与截图不得包含商户密钥或客户资料。
