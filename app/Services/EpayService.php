<?php

namespace App\Services;

use App\Models\Order;

/**
 * 易支付(彩虹易支付/码支付类)聚合网关。
 * 一套 MD5 签名 API 覆盖 支付宝/微信/USDT，页面跳转 + 异步回调发货。
 * 网关地址/PID/密钥由后台「站点设置」配置，未配置时视为未启用(回退 mock)。
 */
class EpayService
{
    /** 本站在线渠道 → 易支付 type（该网关仅 alipay/wxpay/qqpay；未映射的走网关收银台，不传 type） */
    private const TYPE_MAP = ['alipay' => 'alipay', 'wechat' => 'wxpay'];

    public function url(): string
    {
        return rtrim((string) setting('epay_url', ''), '/');
    }

    public function pid(): string
    {
        return (string) setting('epay_pid', '');
    }

    public function key(): string
    {
        return (string) setting('epay_key', '');
    }

    /** 三要素齐全才算已启用 */
    public function configured(): bool
    {
        return $this->url() !== '' && $this->pid() !== '' && $this->key() !== '';
    }

    /** MD5 签名：剔除 sign/sign_type/空值 → 按键 ASCII 升序 → k=v&... 拼接后接密钥（结果小写） */
    public function sign(array $params): string
    {
        unset($params['sign'], $params['sign_type']);
        $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
        ksort($params);

        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = "{$k}={$v}";
        }

        return md5(implode('&', $pairs).$this->key());
    }

    public function verify(array $params): bool
    {
        $sign = $params['sign'] ?? '';

        return $sign !== '' && hash_equals($this->sign($params), (string) $sign);
    }

    /** 生成跳转到网关的支付地址（页面支付 submit.php）。未映射的渠道不传 type → 网关收银台 */
    public function payUrl(Order $order, string $method): string
    {
        $params = [
            'pid' => $this->pid(),
            'out_trade_no' => (string) $order->id,
            'notify_url' => url('/pay/epay/notify'),
            'return_url' => url('/pay/epay/return'),
            'name' => $order->plan?->name ?? "订单#{$order->id}",
            'money' => number_format((float) $order->amount, 2, '.', ''),
            'sign_type' => 'MD5',
        ];
        if (isset(self::TYPE_MAP[$method])) {
            $params['type'] = self::TYPE_MAP[$method];
        }
        $params['sign'] = $this->sign($params);

        return $this->url().'/submit.php?'.http_build_query($params);
    }
}
