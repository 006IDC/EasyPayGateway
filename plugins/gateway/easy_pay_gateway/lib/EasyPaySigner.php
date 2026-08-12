<?php

namespace gateway\easy_pay_gateway\lib;

final class EasyPaySigner
{
    public static function sign(array $params, $merchantKey)
    {
        $merchantKey = (string) $merchantKey;
        $filtered = [];
        foreach ($params as $key => $value) {
            $key = (string) $key;
            if ($key === 'sign' || $key === 'sign_type' || $value === '' || $value === null) {
                continue;
            }
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('签名参数必须是标量');
            }
            $filtered[$key] = (string) $value;
        }
        ksort($filtered, SORT_STRING);
        $pairs = [];
        foreach ($filtered as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        return md5(implode('&', $pairs) . $merchantKey);
    }

    public static function verify(array $params, $merchantKey)
    {
        $received = strtolower((string) ($params['sign'] ?? ''));
        if (preg_match('/^[a-f0-9]{32}$/', $received) !== 1) {
            return false;
        }
        $expected = self::sign($params, $merchantKey);
        return hash_equals($expected, $received);
    }
}
