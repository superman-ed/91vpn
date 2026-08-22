@extends('layouts.user')
@section('title', '商店')
@section('content')
<div class="cards">
    @foreach ($plans as $plan)
    <div class="card">
        <div class="k">{{ $plan->name }}</div>
        <div class="v"><small>¥</small>{{ number_format($plan->price, 0) }} <small>/ {{ $plan->duration_days }}天</small></div>
        <ul style="font-size:13px;color:#6c757d;line-height:1.9;padding-left:18px;margin:14px 0">
            <li>每月 {{ $plan->transfer_gb }} GB 流量</li>
            <li>最多 {{ $plan->ip_limit }} 台设备同时在线</li>
            <li>限速 {{ $plan->speed_limit }} Mbps</li>
            <li>稳定解锁流媒体</li>
        </ul>
        <form method="POST" action="/user/order/create">
            @csrf
            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
            <button class="btn" style="width:100%">购买</button>
        </form>
    </div>
    @endforeach
</div>
@endsection
