<?php

namespace gateway\easy_pay_gateway\lib;

final class Money
{
    public static function normalize($value, $allowZero = false)
    {
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException('金额必须使用十进制字符串');
        }
        $value = trim((string) $value);
        if (preg_match('/^(\d{1,16})(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new \InvalidArgumentException('金额格式无效');
        }
        $integer = ltrim($matches[1], '0');
        if ($integer === '') {
            $integer = '0';
        }
        $fraction = isset($matches[2]) ? str_pad($matches[2], 2, '0') : '00';
        $normalized = $integer . '.' . $fraction;
        if (!$allowZero && $normalized === '0.00') {
            throw new \InvalidArgumentException('支付金额必须大于零');
        }
        return $normalized;
    }

    public static function equals($left, $right)
    {
        return self::normalize($left, true) === self::normalize($right, true);
    }
}
