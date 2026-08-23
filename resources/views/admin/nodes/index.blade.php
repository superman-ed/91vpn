@extends('layouts.admin')
@section('title', '节点管理')
@section('content')
<div class="card">
    <div class="card-header"><h4>节点列表</h4><div class="card-header-action"><a href="/admin/nodes/create" class="btn btn-primary btn-sm">添加节点</a></div></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
        <thead><tr><th>ID</th><th>名称</th><th>地址:端口</th><th>协议</th><th>倍率</th><th>等级门槛</th><th>在线</th><th>操作</th></tr></thead><tbody>
        @forelse($nodes as $n)
        <tr><td>{{ $n->id }}</td><td>{{ $n->name }}</td><td>{{ $n->server }}:{{ $n->port }}</td><td>{{ $n->type }}/{{ $n->net }}</td>
            <td>{{ $n->traffic_rate }}x</td><td>≥{{ $n->node_class }}</td><td>@if($n->online)<span class="badge badge-success">在线</span>@else<span class="badge badge-danger">离线</span>@endif</td>
            <td><a href="/admin/nodes/{{ $n->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                <form method="POST" action="/admin/nodes/{{ $n->id }}" class="d-inline" onsubmit="return confirm('确认删除？')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form></td></tr>
        @empty<tr><td colspan="8" class="text-muted">暂无节点</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
