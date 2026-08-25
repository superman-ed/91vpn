@extends('layouts.admin')
@section('title', $node->exists ? '编辑节点' : '添加节点')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-server text-primary"></i> {{ $node->exists ? '编辑节点' : '添加节点' }}</h4>
    <a href="/admin/nodes" class="btn btn-light" style="border-radius:9px">返回</a>
</div>

<form method="POST" action="{{ $node->exists ? '/admin/nodes/'.$node->id : '/admin/nodes' }}" class="adm-form">@csrf @if($node->exists)@method('PUT')@endif
    <div class="card adm-form-card">
        <div class="card-header"><span class="ic"><i class="fas fa-sliders-h"></i></span><h4>基本信息</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-6"><label>节点名称</label><input name="name" value="{{ old('name', $node->name) }}" class="form-control" placeholder="如：香港01" required></div>
                <div class="form-group col-md-6"><label>连接地址（中转入口域名/IP）</label><input name="server" value="{{ old('server', $node->server) }}" class="form-control" required></div>
                <div class="form-group col-md-3"><label>端口</label><input name="port" type="number" value="{{ old('port', $node->port) }}" class="form-control" required></div>
                <div class="form-group col-md-3"><label>协议</label><select name="type" class="form-control"><option value="vmess">VMess</option></select></div>
                <div class="form-group col-md-3"><label>传输</label><select name="net" class="form-control" id="netSel"><option value="tcp" @selected(old('net', $node->net) == 'tcp')>TCP</option><option value="ws" @selected(old('net', $node->net) == 'ws')>WebSocket</option></select></div>
                <div class="form-group col-md-3"><label>TLS</label><select name="tls" class="form-control"><option value="0" @selected(! old('tls', $node->tls))>关闭</option><option value="1" @selected(old('tls', $node->tls))>开启</option></select></div>
            </div>
            <div class="row" id="wsRow">
                <div class="form-group col-md-6"><label>WS 路径（net=ws 时）</label><input name="path" value="{{ old('path', $node->path) }}" class="form-control" placeholder="/"></div>
                <div class="form-group col-md-6"><label>Host / SNI（ws Host 或 TLS SNI，选填）</label><input name="host" value="{{ old('host', $node->host) }}" class="form-control"></div>
            </div>
        </div>
    </div>

    <div class="card adm-form-card">
        <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#63c76a,#3fae57)"><i class="fas fa-tachometer-alt"></i></span><h4>计费 / 权限</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-3"><label>流量倍率</label><input name="traffic_rate" type="number" step="0.1" value="{{ old('traffic_rate', $node->traffic_rate) }}" class="form-control" required></div>
                <div class="form-group col-md-3"><label>等级门槛（class≥可连）</label><input name="node_class" type="number" value="{{ old('node_class', $node->node_class ?? 0) }}" class="form-control" required></div>
                <div class="form-group col-md-3"><label>节点限速 Mbps（0不限）</label><input name="speed_limit" type="number" value="{{ old('speed_limit', $node->speed_limit ?? 0) }}" class="form-control"></div>
                <div class="form-group col-md-3"><label>分组（0不限）</label><input name="node_group" type="number" value="{{ old('node_group', $node->node_group ?? 0) }}" class="form-control"></div>
                <div class="form-group col-md-3"><label>排序</label><input name="sort" type="number" value="{{ old('sort', $node->sort ?? 0) }}" class="form-control"></div>
            </div>
        </div>
    </div>

    <button class="btn adm-btn"><i class="fas fa-save"></i> 保存</button>
</form>

@if($node->exists)
<div class="card adm-form-card" style="margin-top:20px">
    <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#7c4ddb,#6636c0)"><i class="fas fa-plug"></i></span><h4>节点对接信息</h4></div>
    <div class="card-body adm-form">
        <p class="form-tip">在节点后端(对接脚本)填入以下信息即可上报流量、拉取用户名单。密钥泄露请重新生成。</p>
        <div class="form-group"><label>节点 ID</label><input class="form-control" value="{{ $node->id }}" readonly style="max-width:200px"></div>
        <div class="form-group"><label>通信密钥（secret）</label>
            <div class="input-group" style="max-width:520px"><input class="form-control" value="{{ $node->secret }}" readonly onclick="this.select()">
                <div class="input-group-append"><form method="POST" action="/admin/nodes/{{ $node->id }}/regenerate-secret" onsubmit="return confirm('重新生成后旧密钥立即失效，需同步更新节点后端，确认？')">@csrf<button class="btn btn-outline-danger" style="border-radius:0 9px 9px 0">重新生成</button></form></div>
            </div>
        </div>
        <div class="form-group"><label>用户名单接口</label><input class="form-control" value="{{ url('/mod_mu/users') }}?node_id={{ $node->id }}&key={{ $node->secret }}" readonly onclick="this.select()"></div>
        <div class="form-group mb-0"><label>流量上报接口</label><input class="form-control" value="{{ url('/mod_mu/users/traffic') }}?node_id={{ $node->id }}&key={{ $node->secret }}" readonly onclick="this.select()"></div>
    </div>
</div>
@endif
<script>
(function () {
    var sel = document.getElementById('netSel'), ws = document.getElementById('wsRow');
    function sync() { if (ws) ws.style.display = (sel && sel.value === 'ws') ? '' : 'none'; }
    if (sel) { sel.addEventListener('change', sync); sync(); }
})();
</script>
@endsection
