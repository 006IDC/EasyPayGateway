<?php

define('PLUGIN_DIR', dirname(__DIR__) . '/plugins/gateway/easy_pay_gateway');

require_once PLUGIN_DIR . '/lib/EasyPaySigner.php';
require_once PLUGIN_DIR . '/lib/Money.php';
require_once PLUGIN_DIR . '/service/EasyPayService.php';

use gateway\easy_pay_gateway\lib\EasyPaySigner;
use gateway\easy_pay_gateway\lib\Money;
use gateway\easy_pay_gateway\service\EasyPayService;

function assertTrue($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function htmlAttribute($tag, $name)
{
    $pattern = '/\\b' . preg_quote($name, '/') . '\\s*=\\s*(["\\\'])(.*?)\\1/is';
    if (preg_match($pattern, $tag, $match) !== 1) {
        return null;
    }
    return html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
}

function redirectPaymentFormContract($html, array $requiredFields)
{
    if (preg_match('/<script\\b/i', $html) === 1) {
        return false;
    }
    if (preg_match_all('/<form\\b([^>]*)>(.*?)<\\/form>/is', $html, $forms, PREG_SET_ORDER) === false) {
        return false;
    }

    $redirectForms = [];
    foreach ($forms as $form) {
        $tag = '<form' . $form[1] . '>';
        if (htmlAttribute($tag, 'data-v10-payment-mode') === 'redirect') {
            $redirectForms[] = [$tag, $form[2]];
        }
    }
    if (count($redirectForms) !== 1) {
        return false;
    }

    [$tag, $contents] = $redirectForms[0];
    if (strcasecmp((string) htmlAttribute($tag, 'method'), 'post') !== 0) {
        return false;
    }
    $action = (string) htmlAttribute($tag, 'action');
    if ($action !== 'https://pay.example.com/submit.php'
        || strtolower((string) parse_url($action, PHP_URL_SCHEME)) !== 'https'
    ) {
        return false;
    }

    if (preg_match_all('/<input\\b[^>]*>/is', $contents, $inputs) === false) {
        return false;
    }
    $hiddenFields = [];
    foreach ($inputs[0] as $input) {
        if (preg_match('/\\sdisabled(?:\\s|=|\\/?>)/i', $input) === 1
            || strcasecmp((string) htmlAttribute($input, 'type'), 'hidden') !== 0
        ) {
            return false;
        }
        $name = htmlAttribute($input, 'name');
        if ($name !== null && $name !== '') {
            $hiddenFields[$name] = htmlAttribute($input, 'value');
        }
    }
    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $hiddenFields)) {
            return false;
        }
    }
    return $hiddenFields;
}

function expectNotificationRejectedBeforeCore(array $notification, array $config, $message)
{
    $service = new TestEasyPayService($config);
    $rejected = false;
    try {
        $rejected = !$service->handleNotification($notification);
    } catch (Throwable $e) {
        $rejected = true;
    }
    assertTrue($rejected, $message);
    assertTrue(count($service->coreCalls) === 0, $message . '：不得调用核心入账');
}

$params = [
    'pid' => '1001',
    'type' => 'alipay',
    'out_trade_no' => '202608101234567890',
    'notify_url' => 'https://client.example.com/gateway/easy_pay_gateway/index/notifyHandle',
    'return_url' => 'https://client.example.com/gateway/easy_pay_gateway/index/returnHandle',
    'name' => '测试订单',
    'money' => '1.00',
    'param' => '',
    'sign_type' => 'MD5',
];
$signature = EasyPaySigner::sign($params, 'example-test-key');
assertTrue($signature === 'f5d7b37e2b1b2f6c17ad8ef1850ef84c', '固定签名向量不匹配');
$params['sign'] = $signature;
assertTrue(EasyPaySigner::verify($params, 'example-test-key'), '合法签名应通过');
$params['money'] = '1.01';
assertTrue(!EasyPaySigner::verify($params, 'example-test-key'), '变造金额必须拒绝');

assertTrue(Money::normalize('0001.2') === '1.20', '金额规范化失败');
assertTrue(Money::normalize(2) === '2.00', '整数金额规范化失败');
assertTrue(Money::equals('1.0', '01.00'), '等值金额比较失败');

foreach (['0', '-1.00', '1.001', '1e2', '', '12345678901234567.00'] as $invalid) {
    $failed = false;
    try {
        Money::normalize($invalid);
    } catch (InvalidArgumentException $e) {
        $failed = true;
    }
    assertTrue($failed, '非法金额未被拒绝：' . $invalid);
}

$entry = file_get_contents(PLUGIN_DIR . '/EasyPayGateway.php');
$service = file_get_contents(PLUGIN_DIR . '/service/EasyPayService.php');
assertTrue(strpos($entry, "'version'     => '1.0.1'") !== false, '入口版本错误');
assertTrue(strpos($entry, 'public function upgrade()') !== false, '缺少幂等 upgrade');
assertTrue(strpos($entry, "preg_match('/^[A-Za-z0-9_]*$/', \$prefix)") !== false, '数据库表前缀未限制为安全字符');
assertTrue(strpos($service, "TRADE_SUCCESS") !== false, '缺少最终成功状态校验');
assertTrue(strpos($service, 'expected_amount') !== false, '缺少本地金额快照校验');
assertTrue(strpos($service, 'GET_LOCK') !== false, '缺少并发回调锁');
assertTrue(strpos($service, 'order_pay_handle') !== false, '缺少 V10 核心入账调用');
assertTrue(strpos($service, 'HandleRefund') === false, 'v1 不得暴露不具备幂等键的自动退款');

$pluginConfig = require PLUGIN_DIR . '/config.php';
assertTrue(isset($pluginConfig['submit_url'], $pluginConfig['callback_base_url']), '缺少收银台或回调地址配置');
assertTrue($pluginConfig['merchant_key']['type'] === 'password', '商户密钥必须使用 password 配置类型');
assertTrue($pluginConfig['submit_url']['value'] === 'https://pay.example.com/submit.php', '默认收银台必须使用示例 HTTPS 地址');
assertTrue($pluginConfig['merchant_id']['value'] === '' && $pluginConfig['merchant_key']['value'] === '', '发布包不得预置真实商户凭据');

class TestEasyPayService extends EasyPayService
{
    public $intent;
    public $coreOrderChecks = [];
    public $locks = [];
    public $released = [];
    public $coreCalls = [];
    public $paid = [];
    public $transaction;
    public $orderUnpaid = true;

    protected function rememberIntent($outTradeNo, $orderId, $amount, $paymentType, $merchantId)
    {
        $this->intent = compact('outTradeNo', 'orderId', 'amount', 'paymentType', 'merchantId');
    }

    protected function findMatchingIntent(array $notification, array $merchant)
    {
        if (!Money::equals($notification['money'], '1.00')) {
            throw new RuntimeException('amount mismatch');
        }
        return ['id' => 7, 'order_id' => 42, 'expected_amount' => '1.00'];
    }

    protected function assertCoreOrderLink($outTradeNo, $orderId)
    {
        $this->coreOrderChecks[] = [$outTradeNo, $orderId];
    }

    protected function findTransaction($tradeNo)
    {
        return $this->transaction;
    }

    protected function isOrderUnpaid($orderId)
    {
        return $this->orderUnpaid;
    }

    protected function acquireLock($lockName)
    {
        $this->locks[] = $lockName;
        return true;
    }

    protected function releaseLock($lockName)
    {
        $this->released[] = $lockName;
    }

    protected function invokeCorePayment(array $data)
    {
        $this->coreCalls[] = $data;
        return true;
    }

    protected function markPaid($intentId, array $notification)
    {
        $this->paid[] = [$intentId, $notification];
    }

    protected function audit($event, $outTradeNo)
    {
    }
}

$testConfig = [
    'merchant_id' => '1001',
    'merchant_key' => 'example-test-key',
    'submit_url' => 'https://pay.example.com/submit.php',
    'payment_type' => 'alipay',
    'callback_base_url' => 'https://client.example.com',
];
$testService = new TestEasyPayService($testConfig);
$paymentData = [
    'out_trade_no' => '202608101234567890',
    'finance' => [
        'id' => '42',
        'total' => '1.00',
        'tmp_order_id' => 'unused-order-number-0001',
        'tmp_order_id2' => '202608101234567890',
        'tmp_order_id3' => '1234567890',
    ],
    'product' => ['<b>测试产品</b>'],
];
$form = $testService->createPayment($paymentData);
assertTrue(strpos($form, 'https://pay.example.com/submit.php') !== false, '收银台地址错误');
assertTrue(preg_match_all('/<form\\b/i', $form, $allForms) === 1, '支付 HTML 必须只包含一个提交表单');
assertTrue(substr_count($form, 'data-v10-payment-mode="redirect"') === 1, '必须且只能声明一个 redirect 支付表单');
$requiredFormFields = [
    'pid', 'out_trade_no', 'notify_url', 'return_url', 'name', 'money', 'type', 'sign', 'sign_type',
];
$formFields = redirectPaymentFormContract($form, $requiredFormFields);
assertTrue(is_array($formFields), '支付表单必须是唯一的 POST/HTTPS redirect 表单，且参数均为非 disabled hidden input');
assertTrue($formFields['pid'] === '1001', '商户号隐藏字段丢失');
assertTrue($formFields['out_trade_no'] === '202608101234567890', '商户订单号隐藏字段丢失');
assertTrue($formFields['type'] === 'alipay', '支付通道隐藏字段丢失');
assertTrue($formFields['money'] === '1.00', '金额隐藏字段丢失');
assertTrue($formFields['sign_type'] === 'MD5', '签名类型隐藏字段丢失');
assertTrue(preg_match('/^[a-f0-9]{32}$/', $formFields['sign']) === 1, '签名隐藏字段丢失或格式错误');
assertTrue(
    redirectPaymentFormContract($form . $form, $requiredFormFields) === false,
    '多个 redirect 表单必须被反例合同拒绝'
);
assertTrue(strpos($form, 'notifyHandle') !== false && strpos($form, 'returnHandle') !== false, '回调地址缺失');
assertTrue(strpos($form, '<b>测试产品</b>') === false, '商品名称未转义');
assertTrue($testService->intent['amount'] === '1.00', '金额快照未保存');
assertTrue(count($testService->coreOrderChecks) === 1, '发起支付前未核对核心临时订单');

foreach (['submit_url', 'callback_base_url'] as $urlField) {
    $invalidConfig = $testConfig;
    $invalidConfig[$urlField] = $urlField === 'submit_url'
        ? 'http://pay.example.com/submit.php'
        : 'http://client.example.com';
    $failed = false;
    try {
        (new TestEasyPayService($invalidConfig))->createPayment($paymentData);
    } catch (Throwable $e) {
        $failed = true;
    }
    assertTrue($failed, $urlField . ' 必须拒绝 HTTP 地址');
}

$notify = [
    'pid' => '1001',
    'trade_no' => 'EPAY202608100001',
    'out_trade_no' => '202608101234567890',
    'type' => 'alipay',
    'name' => '测试产品',
    'money' => '1.00',
    'trade_status' => 'TRADE_SUCCESS',
    'sign_type' => 'MD5',
];
$notify['sign'] = EasyPaySigner::sign($notify, $testConfig['merchant_key']);
assertTrue($testService->handleNotification($notify), '正确回调应成功');
assertTrue(count($testService->locks) === 2 && count($testService->released) === 2, '订单锁与流水锁未成对释放');
assertTrue(
    $testService->locks[0] === 'epg:o:' . substr(hash('sha256', '42'), 0, 46),
    '并发锁必须绑定正式订单 ID'
);
assertTrue(count($testService->coreCalls) === 1, '正确回调必须只调用一次核心入账');
assertTrue($testService->coreCalls[0]['currency'] === 'CNY', '回调币种必须固定为 CNY');
assertTrue(count($testService->paid) === 1, '正确回调未标记支付意图');

$duplicate = new TestEasyPayService($testConfig);
$duplicate->transaction = [
    'order_id' => 42,
    'amount' => '1.00',
    'gateway' => 'EasyPayGateway',
];
assertTrue($duplicate->handleNotification($notify), '重复回调应幂等成功');
assertTrue(count($duplicate->coreCalls) === 0, '重复回调不得再次调用核心入账');

$alreadyPaid = new TestEasyPayService($testConfig);
$alreadyPaid->orderUnpaid = false;
assertTrue(!$alreadyPaid->handleNotification($notify), '其他流水已支付的订单必须拒绝');
assertTrue(count($alreadyPaid->coreCalls) === 0, '已被其他流水支付的订单不得调用核心入账');

$tampered = $notify;
$tampered['money'] = '1.01';
expectNotificationRejectedBeforeCore($tampered, $testConfig, '变造金额必须拒绝');

$wrongMerchant = $notify;
$wrongMerchant['pid'] = '1002';
$wrongMerchant['sign'] = EasyPaySigner::sign($wrongMerchant, $testConfig['merchant_key']);
expectNotificationRejectedBeforeCore($wrongMerchant, $testConfig, '商户不匹配必须拒绝');

$pending = $notify;
$pending['trade_status'] = 'WAIT_BUYER_PAY';
$pending['sign'] = EasyPaySigner::sign($pending, $testConfig['merchant_key']);
expectNotificationRejectedBeforeCore($pending, $testConfig, '非成功状态必须拒绝');

$invalidType = $notify;
$invalidType['type'] = 'unknownpay';
$invalidType['sign'] = EasyPaySigner::sign($invalidType, $testConfig['merchant_key']);
expectNotificationRejectedBeforeCore($invalidType, $testConfig, '未知支付通道必须拒绝');

$duplicateMismatch = new TestEasyPayService($testConfig);
$duplicateMismatch->transaction = [
    'order_id' => 99,
    'amount' => '1.00',
    'gateway' => 'EasyPayGateway',
];
assertTrue(!$duplicateMismatch->handleNotification($notify), '同流水不同订单必须拒绝');
assertTrue(count($duplicateMismatch->coreCalls) === 0, '流水冲突不得调用核心入账');

fwrite(STDOUT, "easy_pay_gateway contract tests passed\n");
