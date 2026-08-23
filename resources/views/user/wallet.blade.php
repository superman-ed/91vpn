@extends('layouts.user')
@section('title', '我的钱包')
@section('content')
<div class="row">
    <div class="col-12 col-md-6">
        <div class="card card-statistic-1">
            <div class="card-icon bg-primary"><i class="fas fa-wallet"></i></div>
            <div class="card-wrap"><div class="card-header"><h4>钱包余额</h4></div><div class="card-body">¥{{ number_format($user->money,2) }}</div></div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-header"><h4>模拟充值</h4></div>
            <div class="card-body">
                <form method="POST" action="/user/wallet/recharge" class="form-inline">@csrf
                    <input type="number" name="amount" min="1" step="1" class="form-control mr-2" placeholder="金额" style="width:120px">
                    <button class="btn btn-primary">充值</button>
                </form>
                <small class="text-muted">开发环境模拟，直接到账</small>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header"><h4>购买记录</h4></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>商品</th><th>金额</th><th>状态</th><th>操作</th><th>时间</th></tr></thead>
            <tbody>
            @forelse($orders as $o)
            <tr>
                <td>{{ $o->plan?->name ?? '—' }}</td><td>¥{{ number_format($o->amount,2) }}</td>
                <td>@if($o->status==='paid')<span class="badge badge-success">已支付</span>@elseif($o->status==='pending')<span class="badge badge-warning">待支付</span>@else<span class="badge badge-secondary">已取消</span>@endif</td>
                <td>@if($o->status==='pending')
                    <form method="POST" action="/user/order/{{ $o->id }}/pay-balance" class="d-inline">@csrf<button class="btn btn-outline-primary btn-sm">余额支付</button></form>
                    <form method="POST" action="/user/order/{{ $o->id }}/mock-pay" class="d-inline">@csrf<button class="btn btn-primary btn-sm">模拟付款</button></form>
                    @else — @endif</td>
                <td>{{ $o->created_at?->format('Y-m-d H:i') }}</td>
            </tr>
            @empty<tr><td colspan="5" class="text-muted">暂无订单</td></tr>@endforelse
            </tbody>
        </table>
    </div></div>
</div>
<div class="card">
    <div class="card-header"><h4>余额流水</h4></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>类型</th><th>变动</th><th>变动后</th><th>备注</th><th>时间</th></tr></thead>
            <tbody>
            @forelse($balanceLogs as $l)
            <tr><td>{{ $l->type==='recharge'?'充值':'消费' }}</td><td>{{ $l->amount>0?'+':'' }}{{ number_format($l->amount,2) }}</td>
            <td>¥{{ number_format($l->balance_after,2) }}</td><td>{{ $l->remark }}</td><td>{{ $l->created_at?->format('Y-m-d H:i') }}</td></tr>
            @empty<tr><td colspan="5" class="text-muted">暂无流水</td></tr>@endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
