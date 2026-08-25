<?php

if (! function_exists('bytes_to_gb')) {
    /** 字节转 GB，保留两位小数 */
    function bytes_to_gb(int|float $bytes): float
    {
        return round($bytes / (1024 ** 3), 2);
    }
}

if (! function_exists('human_bytes')) {
    /** 字节自适应格式化为 B/KB/MB/GB/TB，保留两位小数 */
    function human_bytes(int|float $bytes): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (int) $bytes : number_format($bytes, 2)).' '.$units[$i];
    }
}

if (! function_exists('linkify')) {
    /** 纯文本转安全 HTML：转义 + 网址高亮成超链接 + 保留换行 */
    function linkify(?string $text): string
    {
        $escaped = e((string) $text);

        $linked = preg_replace(
            '~(https?://[^\s<]+)~',
            '<a href="$1" target="_blank" rel="noopener" style="color:#6777ef;text-decoration:underline">$1</a>',
            $escaped
        );

        return nl2br($linked);
    }
}

if (! function_exists('period_name')) {
    /** 套餐周期显示名 */
    function period_name(string $period): string
    {
        return match ($period) {
            'month' => '月付',
            'quarter' => '季付',
            'half_year' => '半年付',
            'year' => '年付',
            default => $period,
        };
    }
}

if (! function_exists('setting')) {
    /** 读取站点设置（带缓存），无值回退默认 */
    function setting(string $key, ?string $default = null): ?string
    {
        return \App\Models\Setting::get($key, $default);
    }
}

if (! function_exists('buy_notice_lines')) {
    /** 购买须知（后台可配，每行一条），未配置则用内置默认 */
    function buy_notice_lines(): array
    {
        $default = "流量每 30 天重置一次（从购买日开始计算），未使用的流量不结转到下个周期。\n"
            ."轻量套餐为总量型，有效期内总量不重置（从购买日开始计算），未使用的流量不结转到下个周期。\n"
            ."如当前套餐未到期，新购套餐需等当前套餐过期后自动生效，具体生效时间可以去我的钱包里面查看。\n"
            ."如当月流量用完，要继续使用，请购买流量包（立即生效）或去官网首页点击立即结束当前套餐（需不是多月套餐）。";

        $text = setting('buy_notice', $default);

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
    }
}

if (! function_exists('rebate_rate')) {
    /** 邀请充值返利比例（百分数，后台可配，默认 2.5） */
    function rebate_rate(): float
    {
        return (float) setting('rebate_rate', '2.5');
    }
}

if (! function_exists('signup_bonus')) {
    /** 受邀注册奖励（元，后台可配，默认 1） */
    function signup_bonus(): float
    {
        return (float) setting('signup_bonus', '1');
    }
}

if (! function_exists('smtp_configured')) {
    /** 后台是否已配置 SMTP 发信 */
    function smtp_configured(): bool
    {
        return (string) setting('smtp_host', '') !== '' && (string) setting('smtp_username', '') !== '';
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
