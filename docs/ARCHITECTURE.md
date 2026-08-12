# 架构说明

## 运行文件

V10 运行包只包含以下 7 个文件：

```text
easy_pay_gateway/
├── controller/IndexController.php
├── lib/EasyPaySigner.php
├── lib/Money.php
├── service/EasyPayService.php
├── EasyPayGateway.php
├── EasyPayGateway.png
└── config.php
```

仓库中的测试、文档、构建脚本和 GitHub 配置不进入 V10 插件安装包。

## 支付发起

```text
V10 临时订单
  -> EasyPayGatewayHandle()
  -> 核对正式订单归属与 CNY 金额
  -> 保存支付意图快照
  -> 按易支付 V1 规则签名
  -> 返回唯一的 HTTPS POST redirect 表单
  -> 主题公共支付组件提交到服务商收银台
```

插件不包含自动提交 JavaScript，也不按主题名、客户端类型或 User-Agent 输出不同 HTML。支持 redirect 能力合同的主题可以自动提交；其他主题使用表单内保留的按钮。

## 异步通知

```text
服务商异步通知
  -> 验证 MD5 兼容签名
  -> 核对商户 ID 与 TRADE_SUCCESS
  -> 规范化订单号、流水号、通道和金额
  -> 核对本地支付意图与正式订单关系
  -> 获取订单锁和平台流水锁
  -> 检查 V10 已有交易和订单状态
  -> order_pay_handle()
  -> 标记支付意图已支付
  -> 返回 success
```

同步返回只验证签名并生成 V10 的订单返回地址，不执行入账。

## 数据表

安装和升级都会幂等创建：

```text
{V10_DB_PREFIX}gateway_easy_pay_gateway_intent
```

该表记录订单号、正式订单 ID、预期金额、请求通道、商户 ID、平台流水和状态。`out_trade_no` 与非空 `platform_trade_no` 都有唯一索引，用于支付回调核对和并发冲突检测。

卸载默认不删除该表，因为它属于财务核对证据。

## 金额与签名

`Money` 将金额规范化为最多 16 位整数和 2 位小数的十进制字符串；比较不使用 PHP 浮点数。插件只支持 CNY。

`EasyPaySigner` 排除 `sign`、`sign_type` 和空值，对其余字段按键名字典序拼接，再附加商户密钥计算小写 MD5。这是易支付 V1 的兼容合同，不代表现代密码学设计。

## 并发边界

- 数据库唯一索引约束订单号和平台流水。
- MySQL 命名锁同时绑定正式订单 ID 与平台流水号。
- 已存在且订单、金额、网关均一致的流水被视为成功重试，不会再次调用核心入账。
- 已由其他流水支付的订单或同流水指向不同订单的请求会被拒绝。
