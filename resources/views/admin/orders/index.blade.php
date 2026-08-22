@extends('layouts.admin')
@section('title', '订单管理')
@section('content')
<div class="panel"><h3>订单列表</h3>
<table>
<tr><th>ID</th><th>用户</th><th>套餐</th><th>金额</th><th>状态</th><th>支付方式</th><th>时间</th></tr>
@forelse($orders as $o)
<tr><td>{{ $o->id }}</td><td>{{ $o->user?->email ?? '—' }}</td><td>{{ $o->plan?->name ?? '—' }}</td>
<td>¥{{ number_format($o->amount,2) }}</td>
<td>{{ ['pending'=>'待支付','paid'=>'已支付','cancelled'=>'已取消'][$o->status] ?? $o->status }}</td>
<td>{{ $o->pay_method ?? '—' }}</td><td>{{ $o->created_at?->format('Y-m-d H:i') }}</td></tr>
@empty<tr><td colspan="7" style="color:#acb5c9">暂无订单</td></tr>@endforelse
</table>
<div style="margin-top:16px">{{ $orders->links() }}</div>
</div>
@endsection
