@extends('layouts.admin')
@section('title', '套餐管理')
@section('content')
<div class="panel"><h3>套餐列表 <a href="/admin/plans/create" class="btn sm">+ 添加套餐</a></h3>
<table>
<tr><th>ID</th><th>名称</th><th>价格</th><th>流量</th><th>等级</th><th>限速/设备</th><th>时长</th><th>在售</th><th>操作</th></tr>
@forelse($plans as $p)
<tr><td>{{ $p->id }}</td><td>{{ $p->name }}</td><td>¥{{ $p->price }}</td><td>{{ $p->transfer_gb }}GB</td>
<td>{{ $p->class }}</td><td>{{ $p->speed_limit }}M/{{ $p->ip_limit }}台</td><td>{{ $p->duration_days }}天</td>
<td>{{ $p->on_sale ? '✅' : '❌' }}</td>
<td><a href="/admin/plans/{{ $p->id }}/edit" class="btn ghost sm">编辑</a>
<form method="POST" action="/admin/plans/{{ $p->id }}" style="display:inline" onsubmit="return confirm('确认删除？')">@csrf @method('DELETE')<button class="btn danger sm">删除</button></form></td></tr>
@empty<tr><td colspan="9" style="color:#acb5c9">暂无套餐</td></tr>@endforelse
</table></div>
@endsection
