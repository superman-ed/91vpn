<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

/**
 * 易支付(彩虹易支付/码支付类)聚合网关，兼容易支付 v1 API。
 * 页面跳转支付(submit.php) + 异步回调发货。支付宝→alipay、微信→wxpay 直连；
 * 该网关 type 仅 alipay/wxpay/qqpay，未映射渠道不传 type 走网关收银台。
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

    /**
     * MD5 签名：按文档正文规则——剔除 sign/sign_type/空值 →
     * 按键 ASCII 升序 → k=v&... 拼接（不 urlencode）后接密钥 → md5 小写。
     */
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
        $sign = (string) ($params['sign'] ?? '');

        return $sign !== '' && hash_equals($this->sign($params), $sign);
    }

    /**
     * 主动查单（对账/关单前确认）。三态返回，避免"查询失败"被当成"未支付"而误杀已付订单：
     *   true  = 网关确认已支付成功
     *   false = 网关明确返回"该单未支付"
     *   null  = 查询失败/不确定(未配置、网络异常、HTTP错误、返回码非1)——调用方应保守不关单
     */
    public function isPaidOnGateway(string $outTradeNo): ?bool
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            // 经典彩虹易支付单笔查询：GET api.php?act=order&pid=&key=&out_trade_no=
            // 商户查询以 pid+key 鉴权(key 即商户密钥，走 HTTPS)——与本类的下单(submit.php)、
            // 回调验签同属【经典彩虹】契约。此前这里打的是 /api/EasyPay/queryOrder 且只带
            // orderNo、无 pid/key/sign：那是另一种网关的 API，在经典彩虹上不存在，且无鉴权必被
            // 拒 → 查询恒失败、对账形同虚设。统一回经典彩虹后整个网关契约才自洽。
            // [!] 新网关(等文档)若非经典彩虹，此处需连同下单/回调一并按其文档重对。
            $resp = Http::timeout(10)->get($this->url().'/api.php', [
                'act' => 'order',
                'pid' => $this->pid(),
                'key' => $this->key(),
                'out_trade_no' => $outTradeNo,
            ]);
        } catch (\Throwable $e) {
            return null;   // 网络异常 → 不确定
        }

        if (! $resp->ok()) {
            return null;   // HTTP 错误 → 不确定
        }
        $json = $resp->json() ?? [];
        if ((int) ($json['code'] ?? 0) !== 1) {
            return null;   // 查询未成功返回(含"订单不存在") → 不确定，保守不关单
        }

        return (int) ($json['status'] ?? 0) === 1;   // 顶层 status：1=明确已付 / 0=明确未付
    }

    /** 生成跳转到网关的支付地址（页面支付 submit.php）。未映射的渠道不传 type → 网关收银台 */
    public function payUrl(Order $order, string $method): string
    {
        return $this->buildUrl($order->order_no, $order->plan?->name ?? "订单#{$order->id}", (float) $order->amount, $method);
    }

    /** 通用支付跳转地址(订单/充值共用),$method 为 null 或未映射则跳网关收银台 */
    public function buildUrl(string $outTradeNo, string $name, float $money, ?string $method = null): string
    {
        $params = [
            'pid' => $this->pid(),
            'out_trade_no' => $outTradeNo,
            'notify_url' => url('/pay/epay/notify'),
            'return_url' => url('/pay/epay/return'),
            'name' => $name,
            'money' => number_format($money, 2, '.', ''),
            'sign_type' => 'MD5',
        ];
        if ($method && isset(self::TYPE_MAP[$method])) {
            $params['type'] = self::TYPE_MAP[$method];
        }
        $params['sign'] = $this->sign($params);

        return $this->url().'/submit.php?'.http_build_query($params);
    }
}
