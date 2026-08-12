<?php

namespace gateway\easy_pay_gateway\service;

use gateway\easy_pay_gateway\lib\EasyPaySigner;
use gateway\easy_pay_gateway\lib\Money;
use think\facade\Db;

class EasyPayService
{
    const GATEWAY_NAME = 'EasyPayGateway';

    private $config;

    private $paymentTypes = [
        'cashier', 'alipay', 'wxpay', 'qqpay', 'bank', 'jdpay', 'paypal', 'douyinpay',
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function createPayment(array $param)
    {
        $this->assertSystemCurrency();
        $merchant = $this->merchantConfig();
        $outTradeNo = $this->outTradeNo($param['out_trade_no'] ?? '');
        $finance = isset($param['finance']) && is_array($param['finance']) ? $param['finance'] : [];
        $orderId = $this->positiveInteger($finance['id'] ?? 0, '本地订单不存在');
        $this->assertTemporaryOrderBelongsToFinance($outTradeNo, $finance);
        $amount = Money::normalize($finance['total'] ?? '');
        $baseUrl = $this->callbackBaseUrl($merchant['callback_base_url']);

        $this->assertCoreOrderLink($outTradeNo, $orderId);
        $this->rememberIntent($outTradeNo, $orderId, $amount, $merchant['payment_type'], $merchant['merchant_id']);

        $fields = [
            'pid'          => $merchant['merchant_id'],
            'out_trade_no' => $outTradeNo,
            'notify_url'   => $baseUrl . '/gateway/easy_pay_gateway/index/notifyHandle',
            'return_url'   => $baseUrl . '/gateway/easy_pay_gateway/index/returnHandle',
            'name'         => $this->productName($param['product'] ?? [], $outTradeNo),
            'money'        => $amount,
        ];
        if ($merchant['payment_type'] !== 'cashier') {
            $fields['type'] = $merchant['payment_type'];
        }
        $fields['sign'] = EasyPaySigner::sign($fields, $merchant['merchant_key']);
        $fields['sign_type'] = 'MD5';

        return $this->paymentForm($fields, $merchant['submit_url']);
    }

    public function handleNotification(array $data)
    {
        $this->assertSystemCurrency();
        $merchant = $this->merchantConfig();
        $notification = $this->validateSignedResult($data, $merchant, true);
        $intent = $this->findMatchingIntent($notification, $merchant);
        $lockNames = [
            'epg:o:' . substr(hash('sha256', (string) $intent['order_id']), 0, 46),
            'epg:t:' . substr(hash('sha256', $notification['trade_no']), 0, 46),
        ];
        $locked = [];

        try {
            foreach ($lockNames as $lockName) {
                if (!$this->acquireLock($lockName)) {
                    $this->audit('callback_lock_timeout', $notification['out_trade_no']);
                    return false;
                }
                $locked[] = $lockName;
            }

            $this->assertCoreOrderLink($notification['out_trade_no'], (int) $intent['order_id']);
            $existing = $this->findTransaction($notification['trade_no']);
            if ($existing) {
                $matches = (int) $existing['order_id'] === (int) $intent['order_id']
                    && Money::equals((string) $existing['amount'], $notification['money'])
                    && (string) $existing['gateway'] === self::GATEWAY_NAME;
                if ($matches) {
                    $this->markPaid((int) $intent['id'], $notification);
                    return true;
                }
                $this->audit('duplicate_trade_mismatch', $notification['out_trade_no']);
                return false;
            }
            if (!$this->isOrderUnpaid((int) $intent['order_id'])) {
                $this->audit('order_already_paid_by_other_trade', $notification['out_trade_no']);
                return false;
            }

            $receivedAt = time();
            $ok = $this->invokeCorePayment([
                'tmp_order_id' => $notification['out_trade_no'],
                'amount'       => $notification['money'],
                'trans_id'     => $notification['trade_no'],
                'currency'     => 'CNY',
                'paid_time'    => date('Y-m-d H:i:s', $receivedAt),
                'gateway'      => self::GATEWAY_NAME,
            ]);
            if (!$ok) {
                $this->audit('core_payment_rejected', $notification['out_trade_no']);
                return false;
            }
            $notification['received_at'] = $receivedAt;
            $this->markPaid((int) $intent['id'], $notification);
            return true;
        } catch (\Throwable $e) {
            $this->audit('callback_exception', $notification['out_trade_no'] ?? '');
            return false;
        } finally {
            foreach (array_reverse($locked) as $lockName) {
                $this->releaseLock($lockName);
            }
        }
    }

    public function verifyReturn(array $data)
    {
        try {
            $merchant = $this->merchantConfig();
            $notification = $this->validateSignedResult($data, $merchant, false);
            $this->findMatchingIntent($notification, $merchant);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function merchantConfig()
    {
        $merchantId = trim((string) ($this->config['merchant_id'] ?? ''));
        $merchantKey = (string) ($this->config['merchant_key'] ?? '');
        $submitUrl = $this->submitUrl($this->config['submit_url'] ?? '');
        $paymentType = trim((string) ($this->config['payment_type'] ?? 'cashier'));
        $callbackBaseUrl = trim((string) ($this->config['callback_base_url'] ?? ''));
        if (preg_match('/^[1-9]\d{0,18}$/', $merchantId) !== 1) {
            throw new \RuntimeException('商户 ID 配置无效');
        }
        if (strlen($merchantKey) < 8 || strlen($merchantKey) > 128) {
            throw new \RuntimeException('商户密钥配置无效');
        }
        if (!in_array($paymentType, $this->paymentTypes, true)) {
            throw new \RuntimeException('支付方式配置无效');
        }
        return [
            'merchant_id'       => $merchantId,
            'merchant_key'      => $merchantKey,
            'submit_url'        => $submitUrl,
            'payment_type'      => $paymentType,
            'callback_base_url' => $callbackBaseUrl,
        ];
    }

    private function assertSystemCurrency()
    {
        if (!function_exists('configuration')) {
            return;
        }
        $currency = strtoupper(trim((string) configuration('currency_code')));
        if ($currency !== 'CNY') {
            throw new \RuntimeException('易支付 v1 网关只支持人民币订单');
        }
    }

    private function validateSignedResult(array $data, array $merchant, $requireSuccess)
    {
        if (strtoupper((string) ($data['sign_type'] ?? '')) !== 'MD5') {
            throw new \RuntimeException('签名类型无效');
        }
        if (!EasyPaySigner::verify($data, $merchant['merchant_key'])) {
            throw new \RuntimeException('签名无效');
        }
        if ((string) ($data['pid'] ?? '') !== $merchant['merchant_id']) {
            throw new \RuntimeException('商户不匹配');
        }
        if ($requireSuccess && (string) ($data['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            throw new \RuntimeException('支付尚未成功');
        }
        $outTradeNo = $this->outTradeNo($data['out_trade_no'] ?? '');
        $tradeNo = trim((string) ($data['trade_no'] ?? ''));
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tradeNo) !== 1) {
            throw new \RuntimeException('平台流水号无效');
        }
        $type = trim((string) ($data['type'] ?? ''));
        if ($type === 'cashier' || !in_array($type, $this->paymentTypes, true)) {
            throw new \RuntimeException('支付平台无效');
        }
        return [
            'pid'          => (string) $data['pid'],
            'trade_no'     => $tradeNo,
            'out_trade_no' => $outTradeNo,
            'type'         => $type,
            'money'        => Money::normalize($data['money'] ?? ''),
            'trade_status' => (string) ($data['trade_status'] ?? ''),
        ];
    }

    protected function findMatchingIntent(array $notification, array $merchant)
    {
        $intent = Db::name('gateway_easy_pay_gateway_intent')
            ->where('out_trade_no', $notification['out_trade_no'])
            ->find();
        if (!$intent) {
            throw new \RuntimeException('支付意图不存在');
        }
        if ((string) $intent['merchant_id'] !== $merchant['merchant_id']) {
            throw new \RuntimeException('支付意图商户不匹配');
        }
        if (!Money::equals((string) $intent['expected_amount'], $notification['money'])) {
            throw new \RuntimeException('支付金额不匹配');
        }
        $requested = (string) $intent['requested_type'];
        if ($requested !== 'cashier' && $requested !== $notification['type']) {
            throw new \RuntimeException('支付平台不匹配');
        }
        return $intent;
    }

    protected function rememberIntent($outTradeNo, $orderId, $amount, $paymentType, $merchantId)
    {
        $existing = Db::name('gateway_easy_pay_gateway_intent')
            ->where('out_trade_no', $outTradeNo)
            ->find();
        if ($existing) {
            if ((int) $existing['order_id'] !== (int) $orderId
                || !Money::equals((string) $existing['expected_amount'], $amount)
                || (string) $existing['merchant_id'] !== (string) $merchantId
                || (string) $existing['requested_type'] !== (string) $paymentType
            ) {
                throw new \RuntimeException('临时订单支付快照冲突');
            }
            return;
        }
        $time = time();
        try {
            Db::name('gateway_easy_pay_gateway_intent')->insert([
                'out_trade_no'   => $outTradeNo,
                'order_id'       => $orderId,
                'expected_amount'=> $amount,
                'requested_type' => $paymentType,
                'merchant_id'    => $merchantId,
                'status'         => 'initiated',
                'create_time'    => $time,
                'update_time'    => $time,
            ]);
        } catch (\Throwable $e) {
            $raced = Db::name('gateway_easy_pay_gateway_intent')
                ->where('out_trade_no', $outTradeNo)
                ->find();
            if (!$raced
                || (int) $raced['order_id'] !== (int) $orderId
                || !Money::equals((string) $raced['expected_amount'], $amount)
                || (string) $raced['merchant_id'] !== (string) $merchantId
                || (string) $raced['requested_type'] !== (string) $paymentType
            ) {
                throw $e;
            }
        }
    }

    private function assertTemporaryOrderBelongsToFinance($outTradeNo, array $finance)
    {
        $ids = [];
        foreach (['tmp_order_id', 'tmp_order_id2', 'tmp_order_id3'] as $field) {
            if (isset($finance[$field]) && $finance[$field] !== '') {
                $ids[] = (string) $finance[$field];
            }
        }
        if (!in_array($outTradeNo, $ids, true)) {
            throw new \RuntimeException('临时订单与支付请求不匹配');
        }
    }

    protected function assertCoreOrderLink($outTradeNo, $orderId)
    {
        $tmp = Db::name('order_tmp')->where(function ($query) use ($outTradeNo) {
            $query->where('tmp_order_id', $outTradeNo)
                ->whereOr('tmp_order_id2', $outTradeNo)
                ->whereOr('tmp_order_id3', $outTradeNo);
        })->find();
        if (!$tmp || (int) $tmp['order_id'] !== (int) $orderId) {
            throw new \RuntimeException('本地临时订单不匹配');
        }
        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order || (int) ($order['is_recycle'] ?? 0) !== 0) {
            throw new \RuntimeException('本地订单不可支付');
        }
    }

    protected function markPaid($intentId, array $notification)
    {
        Db::name('gateway_easy_pay_gateway_intent')
            ->where('id', $intentId)
            ->update([
                'status'            => 'paid',
                'platform_trade_no' => $notification['trade_no'],
                'paid_time'         => (int) ($notification['received_at'] ?? time()),
                'update_time'       => time(),
            ]);
    }

    protected function findTransaction($tradeNo)
    {
        return Db::name('transaction')
            ->where('transaction_number', $tradeNo)
            ->find();
    }

    protected function isOrderUnpaid($orderId)
    {
        $order = Db::name('order')->where('id', (int) $orderId)->find();
        return $order && (string) $order['status'] === 'Unpaid' && (int) ($order['is_recycle'] ?? 0) === 0;
    }

    protected function invokeCorePayment(array $data)
    {
        return order_pay_handle($data);
    }

    protected function acquireLock($lockName)
    {
        $rows = Db::query('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        return isset($rows[0]['acquired']) && (int) $rows[0]['acquired'] === 1;
    }

    protected function releaseLock($lockName)
    {
        try {
            Db::query('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        } catch (\Throwable $e) {
        }
    }

    private function callbackBaseUrl($value)
    {
        $parts = parse_url(trim((string) $value));
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || strtolower($parts['scheme']) !== 'https'
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
        ) {
            throw new \RuntimeException('回调站点地址无效');
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        if (preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
            throw new \RuntimeException('回调站点主机无效');
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = isset($parts['path']) ? '/' . trim($parts['path'], '/') : '';
        return $scheme . '://' . $host . $port . rtrim($path, '/');
    }

    private function submitUrl($value)
    {
        $parts = parse_url(trim((string) $value));
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'], $parts['path'])
            || strtolower($parts['scheme']) !== 'https'
            || trim((string) $parts['path'], '/') === ''
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
        ) {
            throw new \RuntimeException('收银台提交地址必须是完整的 HTTPS URL');
        }
        $host = strtolower((string) $parts['host']);
        if (preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
            throw new \RuntimeException('收银台主机名无效');
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = '/' . ltrim((string) $parts['path'], '/');
        return 'https://' . $host . $port . $path;
    }

    private function outTradeNo($value)
    {
        $value = trim((string) $value);
        if (preg_match('/^[A-Za-z0-9_-]{10,64}$/', $value) !== 1) {
            throw new \RuntimeException('临时订单号无效');
        }
        return $value;
    }

    private function positiveInteger($value, $message)
    {
        $value = (string) $value;
        if (preg_match('/^[1-9]\d{0,18}$/', $value) !== 1) {
            throw new \RuntimeException($message);
        }
        return (int) $value;
    }

    private function productName($products, $outTradeNo)
    {
        $name = '';
        if (is_array($products)) {
            foreach ($products as $product) {
                if (is_scalar($product) && trim((string) $product) !== '') {
                    $name = trim((string) $product);
                    break;
                }
            }
        }
        if ($name === '') {
            $name = '订单 ' . $outTradeNo;
        }
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($name)));
        if (function_exists('mb_strcut')) {
            return mb_strcut($name, 0, 127, 'UTF-8');
        }
        return substr($name, 0, 127);
    }

    private function paymentForm(array $fields, $submitUrl)
    {
        $inputs = [];
        foreach ($fields as $key => $value) {
            $inputs[] = '<input type="hidden" name="'
                . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8')
                . '" value="'
                . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
                . '">';
        }
        return '<div class="easy-pay-gateway" role="status" aria-live="polite">'
            . '<p>订单已准备好，请前往收银台完成支付。</p>'
            . '<form data-v10-payment-mode="redirect" method="post" action="'
            . htmlspecialchars((string) $submitUrl, ENT_QUOTES, 'UTF-8')
            . '" target="_top">'
            . implode('', $inputs)
            . '<button type="submit">前往收银台</button>'
            . '</form></div>';
    }

    protected function audit($event, $outTradeNo)
    {
        if (!function_exists('active_log')) {
            return;
        }
        try {
            active_log(
                '[易支付聚合网关] ' . (string) $event,
                'order_tmp',
                preg_match('/^[A-Za-z0-9_-]{10,64}$/', (string) $outTradeNo) ? (string) $outTradeNo : 0
            );
        } catch (\Throwable $e) {
        }
    }
}
