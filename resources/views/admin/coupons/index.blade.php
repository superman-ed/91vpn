@extends('layouts.admin')
@section('title', '优惠券管理')
@section('content')
<div class="card"><div class="card-header"><h4>优惠券</h4><div class="card-header-action"><a href="/admin/coupons/create" class="btn btn-primary btn-sm">生成优惠券</a></div></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>码</th><th>备注</th><th>类型</th><th>额度</th><th>已用/上限</th><th>到期</th><th>状态</th><th>操作</th></tr></thead><tbody>
@forelse($coupons as $c)
<tr><td>{{ $c->code }}</td><td class="text-muted">{{ $c->note ?: '—' }}</td><td>{{ $c->type==='percent'?'百分比':'固定减' }}</td><td>{{ $c->type==='percent'?$c->value.'%':'¥'.$c->value }}</td>
<td>{{ $c->used }}/{{ $c->max_use<0?'∞':$c->max_use }}</td><td>{{ $c->expires_at?->format('Y-m-d') ?? '永久' }}</td>
<td>@if($c->enabled)<span class="badge badge-success">启用</span>@else<span class="badge badge-secondary">停用</span>@endif @if($c->show_on_checkout)<span class="badge badge-info">收银台展示</span>@endif</td>
<td><a href="/admin/coupons/{{ $c->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
<form method="POST" action="/admin/coupons/{{ $c->id }}" class="d-inline" onsubmit="return confirm('删除？')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form></td></tr>
@empty<tr><td colspan="8" class="text-muted">暂无优惠券</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
