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
            <thead><tr><th>ID</th><th>名称</th><th>角色</th><th>地址:端口</th><th>协议</th><th>倍率</th><th>流量(今日/累计)</th><th>整机额度</th><th>等级门槛</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($nodes as $n)
            <tr>
                <td class="text-muted">#{{ $n->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $n->name }}</td>
                {{-- `[!!]` 合并之后中转与落地在同一张表里(ADR-008)，不标角色就只能靠名字猜，
                     而这两类节点的运维含义完全不同：中转【拿不到用户名单】(D-1)、
                     端口来自转发规则、不进任何人的订阅。 --}}
                <td>@if($n->role === 'landing')<span class="adm-pill ok">落地</span>
                    @else<span class="adm-pill warn" title="中转类角色不认证用户、不持有用户名单(D-1)">{{ ['relay'=>'中转','springboard'=>'跳板','front'=>'入口','both'=>'落地+中转'][$n->role] ?? $n->role }}</span>@endif</td>
                <td><span style="font-family:SFMono-Regular,Menlo,Consolas,monospace">{{ $n->server }}@if((int) $n->port > 0):{{ $n->port }}@endif</span>
                    {{-- `[!]` 中转的 port 恒为 0(监听来自转发规则)。写成 "1.2.3.4:0"
                         看着像配错了，实际是对的 —— 所以干脆不显示那个 0。 --}}
                    @if((int) $n->port === 0)<span class="text-muted" style="font-size:12px">端口见转发规则</span>@endif</td>
                <td><span class="adm-pill primary">{{ strtoupper($n->type) }}</span> <span class="adm-pill muted">{{ strtoupper($n->net) }}</span></td>
                <td><span class="adm-pill {{ $n->traffic_rate <= 1 ? 'ok' : 'warn' }}">{{ rtrim(rtrim(number_format($n->traffic_rate, 2), '0'), '.') }}x</span></td>
                <td>
                    @php $tt = $todayByNode->get($n->id); $tot = $totalByNode->get($n->id); @endphp
                    <div style="font-weight:600;color:#34395e">{{ human_bytes($tt->raw ?? 0) }}</div>
                    <div class="text-muted" style="font-size:12px">累计 {{ human_bytes($tot->raw ?? 0) }}@if(($tot->billed ?? 0) > 0)<span title="计费流量(原始×倍率)"> · 计费 {{ human_bytes($tot->billed) }}</span>@endif</div>
                </td>
                {{-- `[!!]` 额度此前只能在表单里【设】，没有任何页面显示【用了多少】——
                     而额度的用途正是防机房超量账单，看不见等于没设。
                     Node::quotaPercent() 早就写好了，一直没有调用方。 --}}
                <td>
                    @php
                        $qgb = (int) ($n->quota_gb ?? 0);
                        $used = $periodBytes[$n->id] ?? 0;
                        $qp = $qgb > 0 ? $used / ($qgb * 1024 ** 3) * 100 : null;
                    @endphp
                    @if($qp === null)<span class="text-muted">不限</span>
                    @else
                        <span class="adm-pill {{ $qp >= 90 ? 'danger' : ($qp >= 70 ? 'warn' : 'ok') }}"
                              title="本计费周期整机网卡用量 / {{ $n->quota_gb }} GB，每月 {{ $n->quota_reset_day ?: 1 }} 号重置">
                            {{ number_format($qp, 0) }}%
                        </span>
                        <div class="text-muted" style="font-size:12px">{{ \App\Support\Fmt::bytes($used, 1) }} / {{ $n->quota_gb }} GB</div>
                    @endif
                </td>
                <td>@if($n->node_class > 0)<span class="adm-pill primary">{{ class_name($n->node_class) }}+</span>@else<span class="text-muted">不限</span>@endif</td>
                <td>@if($n->online)<span class="adm-pill ok">在线</span>@else<span class="adm-pill danger">离线</span>@endif
                    {{-- `[!!]` dest 失效是【静默】的:端口照常监听、这里照常显示"在线",
                         而没有任何客户端能完成握手。所以 dest 挂了必须单独标出来。 --}}
                    @if($n->destHealth() === 'down')
                        <span class="adm-pill danger" title="{{ $n->reported_dest }} 连续失败 {{ $n->reported_dest_failures }} 次">dest 不可达</span>
                    @elseif($n->usesReality() && $n->destHealth() === 'unknown')
                        <span class="adm-pill" title="节点没报过 dest 探活,或上报已过期">dest ?</span>
                    @endif</td>
                <td>
                    <a href="/admin/nodes/{{ $n->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                    {{-- 一键部署（ADR-008 P4b）--}}
              <button type="button" class="btn btn-sm btn-outline-success js-deploy"
                      data-node="{{ $n->id }}" data-name="{{ $n->name }}"
                      data-host="{{ $n->server }}" data-role="{{ $n->role }}"
                      data-port="{{ $n->port }}"
                      data-src="{{ implode(',', $landingSrc[$n->id] ?? []) }}">
                <i class="fas fa-rocket mr-1"></i>部署
              </button>
                    <form method="POST" action="/admin/nodes/{{ $n->id }}" class="d-inline" data-dgr="删除节点「{{ $n->name }}」后，连接该节点的用户将立即无法使用，此操作不可撤销。" data-dgr-word="{{ $n->name }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="11"><div class="adm-empty"><i class="fas fa-server fa-2x mb-2 d-block"></i>暂无节点，点右上角「添加节点」</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@include('admin.nodes._deploy')
