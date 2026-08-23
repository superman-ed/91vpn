@extends('layouts.user')
@section('title', '商店')
@section('content')
<div class="row">
    @foreach ($plans as $plan)
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header"><h4>{{ $plan->name }}</h4></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <span style="font-size:34px;font-weight:800;color:#6777ef">¥{{ number_format($plan->price,0) }}</span>
                    <span class="text-muted">/ {{ $plan->duration_days }}天</span>
                </div>
                <ul class="list-unstyled list-unstyled-border">
                    <li class="media"><i class="fas fa-check text-success mr-2"></i> 每月 {{ $plan->transfer_gb }} GB 流量</li>
                    <li class="media"><i class="fas fa-check text-success mr-2"></i> 最多 {{ $plan->ip_limit }} 台设备</li>
                    <li class="media"><i class="fas fa-check text-success mr-2"></i> 限速 {{ $plan->speed_limit }} Mbps</li>
                    <li class="media"><i class="fas fa-check text-success mr-2"></i> 流媒体解锁</li>
                </ul>
                <form method="POST" action="/user/order/create">@csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <input type="text" name="coupon" class="form-control mb-2" placeholder="优惠码（选填）">
                    <button class="btn btn-primary btn-block">购买</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
