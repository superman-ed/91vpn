@extends('layouts.admin')
@section('title', $node->exists ? '编辑节点' : '添加节点')
@section('content')
<div class="panel">
    <form method="POST" action="{{ $node->exists ? '/admin/nodes/'.$node->id : '/admin/nodes' }}">
        @csrf @if($node->exists)@method('PUT')@endif
        <div class="grid2">
            <div><label>节点名称</label><input name="name" value="{{ old('name', $node->name) }}" style="width:100%" required></div>
            <div><label>连接地址（中转入口域名/IP）</label><input name="server" value="{{ old('server', $node->server) }}" style="width:100%" required></div>
            <div><label>端口</label><input name="port" type="number" value="{{ old('port', $node->port) }}" style="width:100%" required></div>
            <div><label>协议</label><select name="type" style="width:100%"><option value="vmess" selected>vmess</option></select></div>
            <div><label>传输</label><select name="net" style="width:100%"><option value="tcp" @selected(old('net',$node->net)=='tcp')>tcp</option><option value="ws" @selected(old('net',$node->net)=='ws')>ws</option></select></div>
            <div><label>流量倍率</label><input name="traffic_rate" type="number" step="0.1" value="{{ old('traffic_rate', $node->traffic_rate) }}" style="width:100%" required></div>
            <div><label>等级门槛（class≥此值可连）</label><input name="node_class" type="number" value="{{ old('node_class', $node->node_class ?? 0) }}" style="width:100%" required></div>
            <div><label>分组（0=不限）</label><input name="node_group" type="number" value="{{ old('node_group', $node->node_group ?? 0) }}" style="width:100%"></div>
            <div><label>节点限速 Mbps（0=不限）</label><input name="speed_limit" type="number" value="{{ old('speed_limit', $node->speed_limit ?? 0) }}" style="width:100%"></div>
            <div><label>排序</label><input name="sort" type="number" value="{{ old('sort', $node->sort ?? 0) }}" style="width:100%"></div>
        </div>
        <div style="margin-top:20px"><button class="btn">保存</button> <a href="/admin/nodes" class="btn ghost">取消</a></div>
    </form>
</div>
@endsection
