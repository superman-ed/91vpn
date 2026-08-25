@extends('layouts.admin')
@section('title', '在线用户')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-signal text-primary"></i> 在线用户 <span class="text-muted" style="font-size:13px;font-weight:400">最近 {{ $windowSec }} 秒内有流量上报</span></h4>
    <form method="GET" class="adm-search adm-tools">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索用户邮箱" style="min-width:180px">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q)<a href="/admin/online" class="btn btn-light" style="border-radius:9px">清除</a>@endif
        <a href="/admin/online{{ $q ? '?q='.$q : '' }}" class="btn btn-light" style="border-radius:9px" title="刷新"><i class="fas fa-sync-alt"></i></a>
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ $onlineUsers }}</div><div style="font-size:12.5px;opacity:.9"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#fff;margin-right:5px;animation:blink 1.4s infinite"></span>当前在线用户</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ $onlineDevices }}</div><div style="font-size:12.5px;opacity:.9">在线设备（去重 IP）</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#ffb020,#ff9f1a)"><div style="font-size:22px;font-weight:800">{{ human_bytes($todayTraffic) }}</div><div style="font-size:12.5px;opacity:.9">在线用户今日流量</div></div>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>用户</th><th>在线设备 / IP</th><th>所在节点</th><th>今日流量</th><th>总用量 / 配额</th><th>最后活跃</th></tr></thead>
            <tbody>
            @forelse($users as $u)
            @php
                $rows = $alive->get($u->id) ?? collect();
                $ips = $rows->pluck('ip')->unique();
                $nodeNames = $rows->pluck('node.name')->filter()->unique()->values();
                $used = $u->usedTraffic();
                $pct = $u->usagePercent();
            @endphp
            <tr>
                <td style="color:#34395e;font-weight:600">{{ $u->email }}@if($u->is_admin)<span class="adm-pill primary" style="margin-left:6px">管理员</span>@endif</td>
                <td>
                    <span class="adm-pill ok">{{ $ips->count() }} 台</span>
                    <div class="text-muted" style="font-size:12px;font-family:SFMono-Regular,Menlo,Consolas,monospace;margin-top:3px">{{ $ips->take(3)->implode('、') }}@if($ips->count() > 3) …@endif</div>
                </td>
                <td>
                    @forelse($nodeNames as $n)<span class="adm-pill info" style="margin:0 3px 3px 0">{{ $n }}</span>@empty<span class="text-muted">—</span>@endforelse
                </td>
                <td style="font-weight:700;color:#e6960f">{{ human_bytes($u->transfer_today) }}</td>
                <td>
                    <div style="font-size:13px;color:#54667a">{{ human_bytes($used) }} / {{ $u->transfer_enable > 0 ? human_bytes($u->transfer_enable) : '不限' }}</div>
                    @if($u->transfer_enable > 0)
                    <div style="height:5px;background:#eef1f8;border-radius:3px;margin-top:4px;overflow:hidden;max-width:150px"><div style="height:100%;width:{{ $pct }}%;background:{{ $pct >= 90 ? '#fc544b' : ($pct >= 70 ? '#ffb020' : '#3fae57') }}"></div></div>
                    @endif
                </td>
                <td class="text-muted">{{ $u->last_used_at?->diffForHumans() ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="6"><div class="adm-empty"><i class="fas fa-signal fa-2x mb-2 d-block" style="opacity:.4"></i>当前没有在线用户<br><small>用户通过节点跑流量后，{{ $windowSec }} 秒内即会出现在这里</small></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())<div class="adm-foot">{{ $users->links('pagination::bootstrap-4') }}</div>@endif
</div>
<style>@keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: .25; } }</style>
@endsection
