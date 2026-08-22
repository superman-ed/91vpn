@extends('layouts.admin')
@section('title', '优惠券管理')
@section('content')
<div class="panel"><h3>优惠券 <a href="/admin/coupons/create" class="btn sm">+ 生成优惠券</a></h3>
<table>
<tr><th>码</th><th>类型</th><th>额度</th><th>已用/上限</th><th>到期</th><th>状态</th><th>操作</th></tr>
@forelse($coupons as $c)
<tr><td>{{ $c->code }}</td><td>{{ $c->type === 'percent' ? '百分比' : '固定减' }}</td>
<td>{{ $c->type === 'percent' ? $c->value.'%' : '¥'.$c->value }}</td>
<td>{{ $c->used }}/{{ $c->max_use < 0 ? '∞' : $c->max_use }}</td>
<td>{{ $c->expires_at?->format('Y-m-d') ?? '永久' }}</td>
<td>{{ $c->enabled ? '启用' : '停用' }}</td>
<td><form method="POST" action="/admin/coupons/{{ $c->id }}" onsubmit="return confirm('删除？')">@csrf @method('DELETE')<button class="btn danger sm">删除</button></form></td></tr>
@empty<tr><td colspan="7" style="color:#acb5c9">暂无优惠券</td></tr>@endforelse
</table></div>
@endsection
