@extends('layouts.admin')
@section('title', '用户管理')
@section('content')
<div class="card"><div class="card-header"><h4>用户列表</h4>
    <div class="card-header-action"><form method="GET" class="form-inline"><input name="q" value="{{ $q }}" class="form-control mr-2" placeholder="搜索邮箱/昵称"><button class="btn btn-primary btn-sm">搜索</button></form></div></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>ID</th><th>邮箱</th><th>等级</th><th>剩余流量</th><th>到期</th><th>余额</th><th>状态</th><th>操作</th></tr></thead><tbody>
@foreach($users as $u)
<tr><td>{{ $u->id }}</td><td>{{ $u->email }}</td><td>{{ class_name($u->class) }}</td>
<td>{{ number_format(bytes_to_gb(max(0,$u->transfer_enable-$u->u-$u->d)),1) }}G</td><td>{{ $u->class_expire?->format('Y-m-d') ?? '—' }}</td><td>¥{{ number_format($u->money,2) }}</td>
<td>@if($u->banned)<span class="badge badge-danger">封禁</span>@elseif($u->is_admin)<span class="badge badge-primary">管理员</span>@else<span class="badge badge-success">正常</span>@endif</td>
<td><a href="/admin/users/{{ $u->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
<form method="POST" action="/admin/users/{{ $u->id }}/toggle-ban" class="d-inline">@csrf<button class="btn btn-{{ $u->banned?'success':'danger' }} btn-sm">{{ $u->banned?'解封':'封禁' }}</button></form></td></tr>
@endforeach
</tbody></table></div></div>
<div class="card-footer">{{ $users->links() }}</div></div>
@endsection
