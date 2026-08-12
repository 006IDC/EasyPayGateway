<?php

namespace gateway\easy_pay_gateway\controller;

use app\common\model\OrderTmpModel;
use app\home\controller\BaseController;
use gateway\easy_pay_gateway\EasyPayGateway;
use gateway\easy_pay_gateway\service\EasyPayService;

class IndexController extends BaseController
{
    public function notifyHandle()
    {
        try {
            $plugin = new EasyPayGateway();
            $ok = (new EasyPayService($plugin->getConfig()))->handleNotification((array) $_GET);
        } catch (\Throwable $e) {
            $ok = false;
        }
        return response($ok ? 'success' : 'fail', 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    public function returnHandle()
    {
        try {
            $plugin = new EasyPayGateway();
            $service = new EasyPayService($plugin->getConfig());
            $data = (array) $_GET;
            if ($service->verifyReturn($data)) {
                return (new OrderTmpModel())->getGatewayReturnUrl((string) $data['out_trade_no']);
            }
        } catch (\Throwable $e) {
        }
        return response('未能确认支付跳转，请返回订单页面查看支付状态。', 400, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
