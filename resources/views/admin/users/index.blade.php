@extends('layouts.admin')
@section('title', '用户管理')
@section('content')
<div class="panel">
<h3>用户列表</h3>
<form method="GET" style="margin-bottom:14px"><input name="q" value="{{ $q }}" placeholder="搜索邮箱/昵称" style="width:240px"><button class="btn sm">搜索</button></form>
<table>
<tr><th>ID</th><th>邮箱</th><th>等级</th><th>剩余流量</th><th>到期</th><th>余额</th><th>状态</th><th>操作</th></tr>
@foreach($users as $u)
<tr>
<td>{{ $u->id }}</td><td>{{ $u->email }}</td><td>{{ class_name($u->class) }}</td>
<td>{{ number_format(bytes_to_gb(max(0,$u->transfer_enable-$u->u-$u->d)),1) }}G</td>
<td>{{ $u->class_expire?->format('Y-m-d') ?? '—' }}</td>
<td>¥{{ number_format($u->money,2) }}</td>
<td>{{ $u->banned ? '🚫封禁' : ($u->is_admin ? '管理员' : '正常') }}</td>
<td>
<a href="/admin/users/{{ $u->id }}/edit" class="btn ghost sm">编辑</a>
<form method="POST" action="/admin/users/{{ $u->id }}/toggle-ban" style="display:inline">@csrf<button class="btn {{ $u->banned ? '' : 'danger' }} sm">{{ $u->banned ? '解封' : '封禁' }}</button></form>
</td>
</tr>
@endforeach
</table>
<div style="margin-top:16px">{{ $users->links() }}</div>
</div>
@endsection
