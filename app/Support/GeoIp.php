<?php

namespace App\Support;

use Ip2Region;

class GeoIp
{
    private static ?Ip2Region $instance = null;

    /** 解析 IP 归属地（IPv4 + IPv6，走 ip2region 离线库），失败返回空串 */
    public static function locate(?string $ip): string
    {
        if (empty($ip)) {
            return '';
        }

        try {
            $region = self::searcher();
            if ($region === null) {
                return '';
            }
            $raw = $region->simple($ip);   // 例："中国广东省广州市【电信】" / "United StatesCalifornia【Google LLC】"

            return self::format((string) $raw);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function searcher(): ?Ip2Region
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        $v4 = storage_path('app/ip2region/ip2region_v4.xdb');
        $v6 = storage_path('app/ip2region/ip2region_v6.xdb');
        if (! file_exists($v4)) {
            return null;
        }

        return self::$instance = new Ip2Region('file', $v4, file_exists($v6) ? $v6 : null);
    }

    /** 把 ip2region 原始串整理成更友好的显示 */
    private static function format(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        // 运营商在【】里，取出来
        $isp = '';
        if (preg_match('/【(.+?)】/u', $raw, $m)) {
            $isp = $m[1];
            $raw = preg_replace('/【.+?】/u', '', $raw);
        }
        $place = trim($raw);
        // 去掉 DNS服务器 等噪音
        $isp = str_replace(['/DNS服务器', 'DNS服务器'], '', $isp);

        return trim($place.($isp !== '' ? ' '.$isp : ''));
    }
}
