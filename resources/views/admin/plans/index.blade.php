@extends('layouts.admin')
@section('title', '套餐管理')
@section('content')
<div class="card"><div class="card-header"><h4>套餐列表</h4><div class="card-header-action"><a href="/admin/plans/create" class="btn btn-primary btn-sm">添加套餐</a></div></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>ID</th><th>名称</th><th>周期</th><th>价格</th><th>流量</th><th>等级</th><th>限速/设备</th><th>时长</th><th>在售</th><th>操作</th></tr></thead><tbody>
@forelse($plans as $p)
<tr><td>{{ $p->id }}</td><td>{{ $p->name }} @if($p->is_data_pack)<span class="badge badge-info">流量包</span>@endif</td><td><span class="badge badge-light">{{ period_name($p->period) }}</span></td><td>¥{{ $p->price }}</td><td>{{ $p->transfer_gb }}GB @if($p->reset_type==='none' && !$p->is_data_pack)<small class="text-muted">总量</small>@endif</td><td>{{ $p->class }}</td><td>{{ $p->speed_limit }}M/{{ $p->ip_limit }}台</td><td>{{ $p->duration_days }}天</td>
<td>@if($p->on_sale)<span class="badge badge-success">在售</span>@else<span class="badge badge-secondary">下架</span>@endif</td>
<td><a href="/admin/plans/{{ $p->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
<form method="POST" action="/admin/plans/{{ $p->id }}" class="d-inline" onsubmit="return confirm('确认删除？')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form></td></tr>
@empty<tr><td colspan="10" class="text-muted">暂无套餐</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
