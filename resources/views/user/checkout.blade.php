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
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="text-muted">钱包余额</span>
                    <span class="co-balance">¥{{ number_format($user->money, 2) }}</span>
                </div>

                @if($user->money < $order->amount)
                    <div class="alert alert-warning py-2 mb-3">余额不足，请先 <a href="/user/wallet">充值</a> 后再用余额支付。</div>
                    <button class="btn btn-secondary btn-block co-btn mb-2" disabled>余额支付</button>
                @else
                    <form method="POST" action="/user/order/{{ $order->id }}/pay-balance" class="mb-2">@csrf
                        <button class="btn btn-primary btn-block co-btn"><i class="fas fa-wallet"></i> 余额支付</button>
                    </form>
                @endif

                <form method="POST" action="/user/order/{{ $order->id }}/mock-pay">@csrf
                    <button class="btn btn-outline-primary btn-block co-btn"><i class="fas fa-vial"></i> 模拟付款（开发）</button>
                </form>

                <a href="/user/shop" class="btn btn-link btn-block mt-2 text-muted">返回商店</a>
            </div>
        </div>
    </div>
</div>
@endsection
