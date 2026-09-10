<?php

namespace App\Support;

/**
 * 显示用的格式化。
 *
 * [!!] 本文件由 relaypanel 搬入（ADR-008）。第一次搬漏了：视图连同它的
 * `use App\Support\Fmt;` 都搬了过来，**唯独这个类没有** —— 于是中转监控与
 * 转发规则两个页面在【有数据时】直接 500（Class not found）。
 *
 * 空数据时那两处 `Fmt::bytes(...)` 所在的分支根本不执行，所以那组
 * "页面能打开"的用例一直全绿。判据 34：渲染一个空页面不算渲染过这个页面。
 *
 * [!] 不用 `Illuminate\Support\Number::fileSize()` —— 它要求 PHP 的 intl 扩展，
 * 而本应用的镜像里没有装（实测：'The "intl" PHP extension is required'）。
 * 为了一个字节数格式化去重建镜像不划算，何况多一个扩展就多一份要跟的 CVE。
 */
class Fmt
{
    /** 1536 => "1.5 KB"。按 1024 进制，与运维看流量的习惯一致。 */
    public static function bytes(int|float $n, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        // 字节数不带小数：显示 "512 B" 而不是 "512.00 B"。
        return ($i === 0 ? (string) (int) $n : rtrim(rtrim(number_format($n, $precision, '.', ''), '0'), '.'))
            . ' ' . $units[$i];
    }
}
