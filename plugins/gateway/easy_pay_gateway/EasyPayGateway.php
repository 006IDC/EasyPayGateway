<?php

namespace gateway\easy_pay_gateway;

use app\common\lib\Plugin;
use gateway\easy_pay_gateway\service\EasyPayService;
use think\facade\Db;

/**
 * 通用易支付 V1 聚合网关。
 */
class EasyPayGateway extends Plugin
{
    public $info = [
        'name'        => 'EasyPayGateway',
        'title'       => '易支付聚合网关',
        'description' => '通用易支付 V1 页面跳转支付网关，支持聚合收银台和常见固定支付通道。',
        'author'      => 'Community',
        'version'     => '1.0.1',
        'help_url'    => 'https://pay.example.com/docs/easypay-v1',
        'author_url'  => 'https://example.com',
        'url'         => 'https://example.com',
    ];

    // 18 位纯数字临时订单号，兼容易支付 v1 的商户订单号字段。
    public $orderRule = 2;

    public function install()
    {
        $this->migrate();
        return true;
    }

    public function upgrade()
    {
        $this->migrate();
        return true;
    }

    public function uninstall()
    {
        // 支付意图属于财务核对证据，卸载时默认保留。
        return true;
    }

    public function EasyPayGatewayHandle($param)
    {
        try {
            return (new EasyPayService($this->getConfig()))->createPayment((array) $param);
        } catch (\Throwable $e) {
            return [
                'status' => 400,
                'msg'    => '暂时无法发起支付，请稍后重试或联系管理员检查支付配置。',
            ];
        }
    }

    private function migrate()
    {
        $prefix = $this->dbPrefix();
        if (preg_match('/^[A-Za-z0-9_]*$/', $prefix) !== 1) {
            throw new \RuntimeException('数据库表前缀配置无效');
        }
        Db::execute(
            "CREATE TABLE IF NOT EXISTS `{$prefix}gateway_easy_pay_gateway_intent` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `out_trade_no` varchar(64) NOT NULL DEFAULT '',
  `order_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `expected_amount` decimal(18,2) unsigned NOT NULL DEFAULT '0.00',
  `requested_type` varchar(20) NOT NULL DEFAULT 'cashier',
  `merchant_id` varchar(32) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'initiated',
  `platform_trade_no` varchar(64) DEFAULT NULL,
  `paid_time` int(11) unsigned NOT NULL DEFAULT 0,
  `create_time` int(11) unsigned NOT NULL DEFAULT 0,
  `update_time` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_out_trade_no` (`out_trade_no`),
  UNIQUE KEY `uk_platform_trade_no` (`platform_trade_no`),
  KEY `idx_order_status` (`order_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='易支付发起金额与回调核对快照';"
        );
    }

    private function dbPrefix()
    {
        foreach (['database.prefix', 'database.connections.mysql.prefix'] as $key) {
            try {
                $prefix = (string) config($key);
                if ($prefix !== '') {
                    return $prefix;
                }
            } catch (\Throwable $e) {
            }
        }
        try {
            $config = Db::getConfig();
            if (is_array($config) && !empty($config['prefix'])) {
                return (string) $config['prefix'];
            }
        } catch (\Throwable $e) {
        }
        return 'idcsmart_';
    }
}
