@extends('layouts.admin')
@section('title', '工单管理')
@section('content')
<div class="card"><div class="card-header"><h4>工单列表</h4></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>ID</th><th>用户</th><th>标题</th><th>状态</th><th>最后更新</th><th></th></tr></thead><tbody>
@forelse($tickets as $t)
<tr><td>{{ $t->id }}</td><td>{{ $t->user?->email }}</td><td>{{ $t->subject }}</td>
<td>@if($t->status==='open')<span class="badge badge-primary">进行中</span>@else<span class="badge badge-secondary">已关闭</span>@endif</td>
<td>{{ $t->updated_at?->format('Y-m-d H:i') }}</td><td><a href="/admin/tickets/{{ $t->id }}" class="btn btn-outline-primary btn-sm">处理</a></td></tr>
@empty<tr><td colspan="6" class="text-muted">暂无工单</td></tr>@endforelse
</tbody></table></div></div>
<div class="card-footer">{{ $tickets->links() }}</div></div>
@endsection
