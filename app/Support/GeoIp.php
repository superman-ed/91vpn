<?php

namespace App\Support;

class GeoIp
{
    /** 解析 IP 归属地，返回 UTF-8 中文（失败返回空串） */
    public static function locate(?string $ip): string
    {
        if (empty($ip) || ! file_exists(storage_path('app/qqwry.dat'))) {
            return '';
        }
        try {
            $wry = new QQWry();
            $loc = $wry->getlocation($ip);
            if (! $loc) {
                return '';
            }
            // QQWry 返回 GBK，转 UTF-8；country=省市区，area=运营商
            $country = self::toUtf8($loc['country'] ?? '');
            $area = self::toUtf8($loc['area'] ?? '');
            $text = trim($country.' '.$area);
            // 过滤无意义占位
            if (str_contains($text, 'CZ88.NET') || $text === '') {
                return $country ?: '';
            }
            return $text;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function toUtf8(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $enc = mb_detect_encoding($s, ['UTF-8', 'GBK', 'GB2312', 'GB18030'], true);
        if ($enc && $enc !== 'UTF-8') {
            return mb_convert_encoding($s, 'UTF-8', $enc);
        }
        return @mb_convert_encoding($s, 'UTF-8', 'GBK') ?: $s;
    }
}
