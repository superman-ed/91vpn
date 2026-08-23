@extends('layouts.user')
@section('title', '商店')
@section('head')
<style>
.shop-row > [class*="col-"] { display: flex; margin-bottom: 25px; }
.plan-card { width: 100%; margin-bottom: 0; display: flex; flex-direction: column; }
.plan-card .card-body { display: flex; flex-direction: column; flex: 1; }
.plan-price { font-size: 36px; font-weight: 800; color: #6777ef; line-height: 1; }
.plan-feature { padding: 6px 0; border-bottom: 1px solid #f2f4f6; font-size: 14px; }
.plan-feature i { width: 18px; }
</style>
@endsection
@section('content')
@if($plans->isEmpty())
    <div class="card"><div class="card-body text-center text-muted py-5">暂无在售套餐，敬请期待。</div></div>
@else
<div class="row shop-row">
    @foreach ($plans as $plan)
    @php $soldOut = $plan->stock === 0; @endphp
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card plan-card">
            <div class="card-header">
                <h4 style="color:#6777ef">{{ $plan->name }}</h4>
                <div class="card-header-action"><span class="badge badge-light">{{ period_name($plan->period) }}</span></div>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <span class="plan-price">¥{{ rtrim(rtrim(number_format($plan->price, 2), '0'), '.') }}</span>
                    <div class="text-muted mt-1" style="font-size:13px">{{ $plan->duration_days }} 天 · {{ period_name($plan->period) }}</div>
                </div>
                <div class="mb-3">
                    <div class="plan-feature"><i class="fas fa-database text-success"></i> {{ $plan->transfer_gb }} GB 流量</div>
                    <div class="plan-feature"><i class="fas fa-mobile-alt text-success"></i> {{ $plan->ip_limit > 0 ? $plan->ip_limit.' 台设备' : '设备不限' }}</div>
                    <div class="plan-feature"><i class="fas fa-tachometer-alt text-success"></i> {{ $plan->speed_limit > 0 ? $plan->speed_limit.' Mbps' : '不限速' }}</div>
                    <div class="plan-feature"><i class="fas fa-crown text-success"></i> 等级 {{ class_name($plan->class) }}</div>
                    @if($plan->stock > 0)
                    <div class="plan-feature text-warning"><i class="fas fa-fire"></i> 限量剩余 {{ $plan->stock }} 份</div>
                    @endif
                </div>
                <form method="POST" action="/user/order/create" class="mt-auto">@csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    @if($soldOut)
                        <button class="btn btn-light btn-block" disabled>已售罄</button>
                    @else
                        <input type="text" name="coupon" class="form-control mb-2" placeholder="优惠码（选填）">
                        <button class="btn btn-primary btn-block"><i class="fas fa-shopping-cart"></i> 购买</button>
                    @endif
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
