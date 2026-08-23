@extends('layouts.admin')
@section('title', '订单管理')
@section('content')
<div class="card"><div class="card-header"><h4>订单列表</h4></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>ID</th><th>用户</th><th>套餐</th><th>金额</th><th>状态</th><th>支付方式</th><th>时间</th></tr></thead><tbody>
@forelse($orders as $o)
<tr><td>{{ $o->id }}</td><td>{{ $o->user?->email ?? '—' }}</td><td>{{ $o->plan?->name ?? '—' }}</td><td>¥{{ number_format($o->amount,2) }}</td>
<td>@if($o->status==='paid')<span class="badge badge-success">已支付</span>@elseif($o->status==='pending')<span class="badge badge-warning">待支付</span>@else<span class="badge badge-secondary">已取消</span>@endif</td>
<td>{{ $o->pay_method ?? '—' }}</td><td>{{ $o->created_at?->format('Y-m-d H:i') }}</td></tr>
@empty<tr><td colspan="7" class="text-muted">暂无订单</td></tr>@endforelse
</tbody></table></div></div>
<div class="card-footer">{{ $orders->links() }}</div></div>
@endsection
