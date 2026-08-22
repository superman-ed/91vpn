<?php

if (! function_exists('bytes_to_gb')) {
    /** 字节转 GB，保留两位小数 */
    function bytes_to_gb(int|float $bytes): float
    {
        return round($bytes / (1024 ** 3), 2);
    }
}

if (! function_exists('class_name')) {
    /** 等级数字转显示名 */
    function class_name(int $class): string
    {
        return match ($class) {
            0 => '普通用户',
            1 => 'VIP①',
            2 => 'VIP②',
            3 => 'VIP③',
            default => "VIP{$class}",
        };
    }
}
