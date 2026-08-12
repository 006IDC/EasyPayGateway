# 配置手册

## 配置项

| 配置项 | 示例值 | 要求 |
| --- | --- | --- |
| 支付方式名称 | `易支付聚合网关` | 仅用于 V10 前台显示。 |
| 商户 ID | `1001` | 支付服务商分配的 1 至 19 位数字。 |
| 商户密钥 | `example-merchant-key` | 8 至 128 字节；示例值不能用于生产。 |
| 收银台提交地址 | `https://pay.example.com/submit.php` | 完整 HTTPS URL，不能包含账号、密码、查询串或片段。 |
| 支付方式 | `cashier` | 聚合收银台或一个固定通道。 |
| 回调站点地址 | `https://client.example.com` | 当前 V10 站点的公网 HTTPS 根地址，可带部署子路径。 |

仓库不会保存真实商户 ID、密钥或生产域名。请在部署后的 V10 后台填写，并限制后台账户与数据库备份的访问权限。

## 支付通道

| 值 | 用途 |
| --- | --- |
| `cashier` | 不提交 `type` 字段，由付款人在聚合收银台选择实际通道。 |
| `alipay` | 支付宝。 |
| `wxpay` | 微信支付。 |
| `qqpay` | QQ 钱包。 |
| `bank` | 银联支付。 |
| `jdpay` | 京东支付。 |
| `paypal` | PayPal。 |
| `douyinpay` | 抖音支付。 |

固定通道是否可用取决于服务商实现。启用前应向服务商确认其接口字段、回调字段和成功状态与易支付 V1 兼容。

## 回调地址

假设“回调站点地址”为 `https://client.example.com`，插件生成：

```text
https://client.example.com/gateway/easy_pay_gateway/index/notifyHandle
https://client.example.com/gateway/easy_pay_gateway/index/returnHandle
```

如果 V10 部署在 `/billing` 子路径，可填写：

```text
https://client.example.com/billing
```

插件会在该地址后附加固定 Gateway 路由。

## URL 校验

为减少配置误用，插件拒绝：

- `http://` 地址；
- 缺少主机名或提交路径的收银台地址；
- URL 中的用户名或密码；
- 查询串和片段；
- 非法主机名字符。

如服务商要求在提交 URL 上预置查询参数，本插件不能直接兼容，应先确认能否改用标准路径式 HTTPS 提交端点。

## 密钥轮换

更换商户密钥时，支付服务商和 V10 插件配置必须在同一变更窗口完成。轮换前创建、轮换后才回调的订单可能因旧密钥验签失败而无法自动入账。建议：

1. 暂停新支付入口。
2. 等待在途订单完成或过期。
3. 同步更新服务商与插件配置。
4. 用新订单完成一次发起、回调和重复回调测试。
5. 恢复支付入口。
