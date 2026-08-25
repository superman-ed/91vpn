@extends('layouts.admin')
@section('title', '节点管理')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-server text-primary"></i> 节点管理 <span class="text-muted" style="font-size:13px;font-weight:400">共 {{ $nodes->count() }} 个</span></h4>
    <a href="/admin/nodes/create" class="btn adm-btn"><i class="fas fa-plus"></i> 添加节点</a>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>ID</th><th>名称</th><th>地址:端口</th><th>协议</th><th>倍率</th><th>流量(今日/累计)</th><th>等级门槛</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($nodes as $n)
            <tr>
                <td class="text-muted">#{{ $n->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $n->name }}</td>
                <td><span style="font-family:SFMono-Regular,Menlo,Consolas,monospace">{{ $n->server }}:{{ $n->port }}</span></td>
                <td><span class="adm-pill primary">{{ strtoupper($n->type) }}</span> <span class="adm-pill muted">{{ strtoupper($n->net) }}</span></td>
                <td><span class="adm-pill {{ $n->traffic_rate <= 1 ? 'ok' : 'warn' }}">{{ rtrim(rtrim(number_format($n->traffic_rate, 2), '0'), '.') }}x</span></td>
                <td>
                    @php $tt = $todayByNode->get($n->id); $tot = $totalByNode->get($n->id); @endphp
                    <div style="font-weight:600;color:#34395e">{{ human_bytes($tt->raw ?? 0) }}</div>
                    <div class="text-muted" style="font-size:12px">累计 {{ human_bytes($tot->raw ?? 0) }}@if(($tot->billed ?? 0) > 0)<span title="计费流量(原始×倍率)"> · 计费 {{ human_bytes($tot->billed) }}</span>@endif</div>
                </td>
                <td>@if($n->node_class > 0)<span class="adm-pill primary">{{ class_name($n->node_class) }}+</span>@else<span class="text-muted">不限</span>@endif</td>
                <td>@if($n->online)<span class="adm-pill ok">在线</span>@else<span class="adm-pill danger">离线</span>@endif</td>
                <td>
                    <a href="/admin/nodes/{{ $n->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                    <form method="POST" action="/admin/nodes/{{ $n->id }}" class="d-inline" data-confirm="删除节点「{{ $n->name }}」后，连接该节点的用户将立即无法使用，此操作不可撤销。" data-confirm-word="{{ $n->name }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="9"><div class="adm-empty"><i class="fas fa-server fa-2x mb-2 d-block"></i>暂无节点，点右上角「添加节点」</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
