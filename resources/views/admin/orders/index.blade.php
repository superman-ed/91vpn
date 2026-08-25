@extends('layouts.admin')
@section('title', '订单管理')
@section('content')
@php
    $tabs = ['' => '全部', 'paid' => '已支付', 'pending' => '待支付', 'queued' => '排队中', 'cancelled' => '已取消'];
    $payName = ['balance' => '余额', 'alipay' => '支付宝', 'wechat' => '微信', 'wxpay' => '微信', 'usdt' => 'USDT', 'epay' => '网关', 'mock' => '模拟', 'free' => '免费'];
@endphp
<div class="adm-head">
    <h4><i class="fas fa-receipt text-primary"></i> 订单管理</h4>
    <div class="adm-tools">
        @foreach($tabs as $k => $label)
        <a href="/admin/orders{{ $k ? '?status='.$k : '' }}" class="btn btn-sm {{ (string) $status === $k ? 'adm-btn' : 'btn-light' }}" style="border-radius:9px">{{ $label }} <span style="opacity:.7">{{ $k ? ($counts[$k] ?? 0) : $counts['all'] }}</span></a>
        @endforeach
    </div>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>ID</th><th>用户</th><th>套餐</th><th>金额</th><th>状态</th><th>支付方式</th><th>时间</th></tr></thead>
            <tbody>
            @forelse($orders as $o)
            <tr>
                <td class="text-muted">#{{ $o->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $o->user?->email ?? '—' }}</td>
                <td>{{ $o->plan?->name ?? '—' }}</td>
                <td style="font-weight:700;color:#34395e">¥{{ number_format($o->amount, 2) }}</td>
                <td>
                    @switch($o->status)
                        @case('paid')<span class="adm-pill ok">已支付</span>@break
                        @case('queued')<span class="adm-pill info">排队中</span>@break
                        @case('pending')<span class="adm-pill warn">待支付</span>@break
                        @default<span class="adm-pill muted">已取消</span>
                    @endswitch
                </td>
                <td>{{ $o->pay_method ? ($payName[$o->pay_method] ?? $o->pay_method) : '—' }}</td>
                <td class="text-muted">{{ $o->created_at?->format('Y-m-d H:i') }}</td>
            </tr>
            @empty<tr><td colspan="7"><div class="adm-empty"><i class="fas fa-receipt fa-2x mb-2 d-block"></i>暂无订单</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div class="adm-foot">{{ $orders->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
