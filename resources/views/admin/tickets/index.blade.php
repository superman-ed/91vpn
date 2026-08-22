@extends('layouts.admin')
@section('title', '工单管理')
@section('content')
<div class="panel"><h3>工单列表</h3>
<table>
<tr><th>ID</th><th>用户</th><th>标题</th><th>状态</th><th>最后更新</th><th></th></tr>
@forelse($tickets as $t)
<tr><td>{{ $t->id }}</td><td>{{ $t->user?->email }}</td><td>{{ $t->subject }}</td>
<td>{{ $t->status === 'open' ? '进行中' : '已关闭' }}</td><td>{{ $t->updated_at?->format('Y-m-d H:i') }}</td>
<td><a href="/admin/tickets/{{ $t->id }}" class="btn ghost sm">处理</a></td></tr>
@empty<tr><td colspan="6" style="color:#acb5c9">暂无工单</td></tr>@endforelse
</table>
<div style="margin-top:16px">{{ $tickets->links() }}</div></div>
@endsection
