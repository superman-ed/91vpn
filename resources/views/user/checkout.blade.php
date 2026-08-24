@extends('layouts.user')
@section('title', '订单结算')
@section('head')
<style>
.co-summary .row-line { display: flex; justify-content: space-between; align-items: baseline; padding: 10px 0; border-bottom: 1px dashed #eef0f5; font-size: 14px; }
.co-summary .row-line:last-child { border-bottom: none; }
.co-summary .row-line .lbl { color: #7a869a; }
.co-summary .row-line .val { color: #34395e; font-weight: 600; }
.co-summary .row-line.discount .val { color: #63c76a; }
.co-pay { font-size: 30px; font-weight: 800; color: #6777ef; line-height: 1; }
.co-balance { font-size: 20px; font-weight: 700; color: #34395e; }
.co-coupon .form-control { border-radius: 8px; }
.co-btn { border-radius: 9px; padding: 11px; font-weight: 700; }
.pay-methods { margin: 4px 0 14px; }
.pm { display: flex; align-items: center; gap: 10px; width: 100%; padding: 11px 12px; margin-bottom: 8px; border: 1.5px solid #eef0f5; border-radius: 10px; cursor: pointer; transition: border-color .15s, background .15s; }
.pm:hover { border-color: #c9d0f7; }
.pm.active { border-color: #6777ef; background: #f5f6ff; }
.pm.disabled { opacity: .5; cursor: not-allowed; }
.pm input { display: none; }
.pm-ic { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 15px; flex-shrink: 0; }
.pm-balance { background: #6777ef; } .pm-alipay { background: #1677ff; } .pm-wechat { background: #07c160; } .pm-usdt { background: #26a17b; }
.pm-name { font-weight: 600; color: #34395e; }
.pm-extra { margin-left: auto; color: #98a6ad; font-size: 13px; }
.coupon-note { font-size: 12.5px; line-height: 1.9; }
.coupon-note .code { color: #6777ef; font-weight: 700; letter-spacing: .5px; }
</style>
@endsection
@section('content')
@php
    $plan = $order->plan;
    $base = (float) $plan->price;
    $discount = max(0, $base - (float) $order->amount);
@endphp
<div class="row">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header"><h4>订单详情</h4></div>
            <div class="card-body co-summary">
                <div class="row-line"><span class="lbl">套餐</span><span class="val">{{ $plan->name }}</span></div>
                <div class="row-line"><span class="lbl">时长</span><span class="val">{{ period_name($order->period) }} · {{ $plan->duration_days }} 天</span></div>
                <div class="row-line"><span class="lbl">流量</span><span class="val">每月 {{ $plan->transfer_gb }}GB</span></div>
                <div class="row-line"><span class="lbl">原价</span><span class="val">¥{{ number_format($base, 2) }}</span></div>
                @if($order->coupon_id)
                <div class="row-line discount"><span class="lbl">优惠码 {{ $order->coupon?->code }}</span><span class="val">-¥{{ number_format($discount, 2) }}</span></div>
                @endif
            </div>
        </div>

        <div class="card co-coupon">
            <div class="card-header"><h4>优惠码</h4></div>
            <div class="card-body">
                <form method="POST" action="/user/order/{{ $order->id }}/coupon" class="form-inline">@csrf
                    <input type="text" name="coupon" value="{{ old('coupon', $order->coupon?->code) }}" class="form-control mr-2 mb-2 @error('coupon') is-invalid @enderror" placeholder="输入优惠码" style="min-width:180px">
                    <button class="btn btn-outline-primary mb-2 mr-2">应用</button>
                    @if($order->coupon_id)
                    <button type="submit" name="coupon" value="" class="btn btn-light mb-2">移除</button>
                    @endif
                    @error('coupon')<div class="invalid-feedback d-block w-100">{{ $message }}</div>@enderror
                </form>
                <div class="coupon-note text-muted mt-2">
                    <div>VIP ①②③ 半年套餐 95 折优惠码：<span class="code">XXXXXX</span></div>
                    <div>VIP ①②③ 年付套餐 90 折优惠码：<span class="code">XXXXXX</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header"><h4>支付</h4></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-baseline mb-4">
                    <span class="text-muted">应付金额</span>
                    <span class="co-pay">¥{{ number_format($order->amount, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline mb-3">
                    <span class="text-muted">钱包余额</span>
                    <span class="co-balance">¥{{ number_format($user->money, 2) }}</span>
                </div>

                <form method="POST" action="/user/order/{{ $order->id }}/pay" id="payForm">@csrf
                    <div class="pay-methods">
                        <label class="pm active" data-method="balance">
                            <input type="radio" name="method" value="balance" checked>
                            <span class="pm-ic pm-balance"><i class="fas fa-wallet"></i></span>
                            <span class="pm-name">余额支付</span>
                            <span class="pm-extra">¥{{ number_format($user->money, 2) }}</span>
                        </label>
                        <label class="pm" data-method="alipay">
                            <input type="radio" name="method" value="alipay">
                            <span class="pm-ic pm-alipay"><i class="fab fa-alipay"></i></span>
                            <span class="pm-name">支付宝</span>
                        </label>
                        <label class="pm" data-method="wechat">
                            <input type="radio" name="method" value="wechat">
                            <span class="pm-ic pm-wechat"><i class="fab fa-weixin"></i></span>
                            <span class="pm-name">微信支付</span>
                        </label>
                        <label class="pm" data-method="usdt">
                            <input type="radio" name="method" value="usdt">
                            <span class="pm-ic pm-usdt"><i class="fas fa-coins"></i></span>
                            <span class="pm-name">USDT</span>
                        </label>
                    </div>

                    <div class="alert alert-warning py-2 mb-3" data-lowbalance style="display:none">余额不足，请先 <a href="/user/wallet">充值</a> 或换其它支付方式。</div>
                    @error('method')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                    <button class="btn btn-primary btn-block co-btn" data-confirm><i class="fas fa-lock"></i> 确认支付 ¥{{ number_format($order->amount, 2) }}</button>
                </form>

                <form method="POST" action="/user/order/{{ $order->id }}/mock-pay" class="mt-2">@csrf
                    <button class="btn btn-link btn-block text-muted"><i class="fas fa-vial"></i> 模拟付款（开发）</button>
                </form>

                <a href="/user/shop" class="btn btn-link btn-block text-muted">返回商店</a>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var amount = {{ (float) $order->amount }}, balance = {{ (float) $user->money }};
    var form = document.getElementById('payForm');
    if (!form) return;
    var confirmBtn = form.querySelector('[data-confirm]');
    var lowWarn = form.querySelector('[data-lowbalance]');

    function pick(method) {
        form.querySelectorAll('.pm').forEach(function (el) { el.classList.toggle('active', el.dataset.method === method); });
        var lowBalance = method === 'balance' && balance < amount;
        if (lowWarn) lowWarn.style.display = lowBalance ? '' : 'none';
        confirmBtn.disabled = lowBalance;
    }
    form.querySelectorAll('input[name="method"]').forEach(function (radio) {
        radio.addEventListener('change', function () { pick(this.value); });
    });
    pick('balance');
})();
</script>
@endsection
