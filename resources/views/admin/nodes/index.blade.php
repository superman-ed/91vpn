@extends('layouts.admin')
@section('title', '节点管理')
@section('content')
<div class="panel">
    <h3>节点列表 <a href="/admin/nodes/create" class="btn sm">+ 添加节点</a></h3>
    <table>
        <tr><th>ID</th><th>名称</th><th>地址:端口</th><th>协议</th><th>倍率</th><th>等级门槛</th><th>在线</th><th>操作</th></tr>
        @forelse($nodes as $n)
        <tr>
            <td>{{ $n->id }}</td><td>{{ $n->name }}</td>
            <td>{{ $n->server }}:{{ $n->port }}</td>
            <td>{{ $n->type }}/{{ $n->net }}</td>
            <td>{{ $n->traffic_rate }}x</td>
            <td>≥{{ $n->node_class }}</td>
            <td>{{ $n->online ? '✅' : '❌' }}</td>
            <td>
                <a href="/admin/nodes/{{ $n->id }}/edit" class="btn ghost sm">编辑</a>
                <form method="POST" action="/admin/nodes/{{ $n->id }}" style="display:inline" onsubmit="return confirm('确认删除？')">@csrf @method('DELETE')<button class="btn danger sm">删除</button></form>
            </td>
        </tr>
        @empty<tr><td colspan="8" style="color:#acb5c9">暂无节点</td></tr>@endforelse
    </table>
</div>
@endsection
