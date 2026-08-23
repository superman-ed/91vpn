@extends('layouts.admin')
@section('title', $node->exists ? '编辑节点' : '添加节点')
@section('content')
<div class="card"><div class="card-body">
<form method="POST" action="{{ $node->exists ? '/admin/nodes/'.$node->id : '/admin/nodes' }}">@csrf @if($node->exists)@method('PUT')@endif
<div class="row">
    <div class="form-group col-md-6"><label>节点名称</label><input name="name" value="{{ old('name',$node->name) }}" class="form-control" required></div>
    <div class="form-group col-md-6"><label>连接地址（中转入口域名/IP）</label><input name="server" value="{{ old('server',$node->server) }}" class="form-control" required></div>
    <div class="form-group col-md-4"><label>端口</label><input name="port" type="number" value="{{ old('port',$node->port) }}" class="form-control" required></div>
    <div class="form-group col-md-4"><label>协议</label><select name="type" class="form-control"><option value="vmess">vmess</option></select></div>
    <div class="form-group col-md-4"><label>传输</label><select name="net" class="form-control"><option value="tcp" @selected(old('net',$node->net)=='tcp')>tcp</option><option value="ws" @selected(old('net',$node->net)=='ws')>ws</option></select></div>
    <div class="form-group col-md-4"><label>流量倍率</label><input name="traffic_rate" type="number" step="0.1" value="{{ old('traffic_rate',$node->traffic_rate) }}" class="form-control" required></div>
    <div class="form-group col-md-4"><label>等级门槛（class≥此值可连）</label><input name="node_class" type="number" value="{{ old('node_class',$node->node_class ?? 0) }}" class="form-control" required></div>
    <div class="form-group col-md-4"><label>分组（0=不限）</label><input name="node_group" type="number" value="{{ old('node_group',$node->node_group ?? 0) }}" class="form-control"></div>
    <div class="form-group col-md-4"><label>节点限速 Mbps（0=不限）</label><input name="speed_limit" type="number" value="{{ old('speed_limit',$node->speed_limit ?? 0) }}" class="form-control"></div>
    <div class="form-group col-md-4"><label>排序</label><input name="sort" type="number" value="{{ old('sort',$node->sort ?? 0) }}" class="form-control"></div>
</div>
<button class="btn btn-primary">保存</button> <a href="/admin/nodes" class="btn btn-light">取消</a>
</form>
</div></div>
@endsection
