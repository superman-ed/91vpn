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

@php
    $presets = [
        '今日' => [today()->toDateString(), today()->toDateString()],
        '近7天' => [today()->subDays(6)->toDateString(), today()->toDateString()],
        '近30天' => [today()->subDays(29)->toDateString(), today()->toDateString()],
        '本月' => [today()->startOfMonth()->toDateString(), today()->toDateString()],
    ];
    $isRange = fn ($r) => $from === $r[0] && $to === $r[1];
@endphp

{{-- 实时指标（不随日期变化） --}}
<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px">
    <div style="flex:1;min-width:145px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ $onlineUsers }}</div><div style="font-size:12.5px;opacity:.9"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#fff;margin-right:5px;animation:blink 1.4s infinite"></span>当前在线用户</div></div>
    <div style="flex:1;min-width:145px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ $onlineDevices }}</div><div style="font-size:12.5px;opacity:.9">在线设备（去重 IP）</div></div>
    <div style="flex:1;min-width:145px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#ffb020,#ff9f1a)"><div style="font-size:22px;font-weight:800">{{ human_bytes($onlineTodayTraffic) }}</div><div style="font-size:12.5px;opacity:.9">在线用户今日流量</div></div>
</div>

{{-- 区间日期选择 --}}
<form method="GET" class="d-flex align-items-center flex-wrap" style="gap:6px;margin-bottom:14px">
    @if($q)<input type="hidden" name="q" value="{{ $q }}">@endif
    <span class="text-muted" style="font-size:13px"><i class="fas fa-calendar-alt"></i> 统计区间</span>
    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm" style="width:auto;border-radius:8px">
    <span class="text-muted">~</span>
    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm" style="width:auto;border-radius:8px">
    <button class="btn btn-sm adm-btn" style="border-radius:8px">查询</button>
    @foreach($presets as $label => $range)
    <a href="/admin/online?from={{ $range[0] }}&to={{ $range[1] }}{{ $q ? '&q='.$q : '' }}" class="btn btn-sm {{ $isRange($range) ? 'adm-btn' : 'btn-light' }}" style="border-radius:8px">{{ $label }}</a>
    @endforeach
</form>

{{-- 区间统计（随日期变化） --}}
<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:145px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#e0567b,#c93f66)"><div style="font-size:22px;font-weight:800">{{ human_bytes($rangeTraffic) }}</div><div style="font-size:12.5px;opacity:.9">区间总流量 · {{ $rangeDays }} 天</div></div>
    <div style="flex:1;min-width:145px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#3aa0c7,#2a86ab)"><div style="font-size:22px;font-weight:800">{{ human_bytes($rangeAvgTraffic) }}</div><div style="font-size:12.5px;opacity:.9">日均流量</div></div>
    <div style="flex:1;min-width:145px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#7c4ddb,#6636c0)"><div style="font-size:22px;font-weight:800">{{ $peakDau }}</div><div style="font-size:12.5px;opacity:.9">日活峰值(单日最高)</div></div>
    <div style="flex:1;min-width:145px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#2ec27e,#25a06a)"><div style="font-size:22px;font-weight:800">{{ $peakOnline }}</div><div style="font-size:12.5px;opacity:.9">在线峰值(单日最高)</div></div>
</div>

<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-chart-bar text-primary"></i> 日活 · 在线趋势 <span class="text-muted" style="font-weight:400;font-size:12px">（{{ $from }} ~ {{ $to }}）</span></h4>
        <div style="font-size:12px;color:#98a6ad"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#c7d0f5;margin-right:4px;vertical-align:middle"></span>日活 DAU&nbsp;&nbsp;<span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#3fae57;margin-right:4px;vertical-align:middle"></span>在线峰值</div>
    </div>
    <div style="padding:14px 20px 18px">
        <div style="display:flex;align-items:flex-end;gap:3px;height:130px">
            @foreach($trend as $t)
            @php $duH = round($t['dau'] / $trendMax * 118); $pkH = round($t['peak'] / $trendMax * 118); @endphp
            <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;position:relative" title="{{ $t['label'] }}　日活 {{ $t['dau'] }}　峰值 {{ $t['peak'] }}">
                <div style="width:100%;max-width:16px;position:relative;height:118px;display:flex;align-items:flex-end;justify-content:center">
                    <div style="position:absolute;bottom:0;width:100%;border-radius:3px 3px 0 0;background:#c7d0f5;height:{{ max($duH, 2) }}px"></div>
                    <div style="position:absolute;bottom:0;width:60%;border-radius:3px 3px 0 0;background:#3fae57;height:{{ $pkH }}px"></div>
                </div>
            </div>
            @endforeach
        </div>
        <div style="display:flex;gap:3px;margin-top:6px">
            @foreach($trend as $i => $t)
            <div style="flex:1;text-align:center;font-size:9.5px;color:#b5bdc9">{{ $i % 3 === 0 ? $t['label'] : '' }}</div>
            @endforeach
        </div>
        @if($trend->sum('dau') === 0 && $trend->sum('peak') === 0)
        <p class="text-muted" style="text-align:center;margin:8px 0 0;font-size:12.5px">暂无历史数据 —— 定时任务 <code>stats:snapshot</code> 每 10 分钟采样一次，运行一段时间后即有曲线。</p>
        @endif
    </div>
</div>

@php $trafMax = max(1, (int) $trend->max('traffic')); @endphp
<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 0"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-tachometer-alt text-primary"></i> 区间流量消耗 <span class="text-muted" style="font-weight:400;font-size:12px">（原始带宽）</span></h4></div>
    <div style="padding:14px 20px 18px">
        <div style="display:flex;align-items:flex-end;gap:3px;height:110px">
            @foreach($trend as $t)
            <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%" title="{{ $t['label'] }}　{{ human_bytes($t['traffic']) }}">
                <div style="width:100%;max-width:16px;border-radius:3px 3px 0 0;background:linear-gradient(180deg,#e0567b,#c93f66);height:{{ max(round($t['traffic'] / $trafMax * 100), $t['traffic'] > 0 ? 2 : 0) }}px"></div>
            </div>
            @endforeach
        </div>
        <div style="display:flex;gap:3px;margin-top:6px">
            @foreach($trend as $i => $t)<div style="flex:1;text-align:center;font-size:9.5px;color:#b5bdc9">{{ $i % 3 === 0 ? $t['label'] : '' }}</div>@endforeach
        </div>
    </div>
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
    @include('admin.partials.pager', ['p' => $users])
</div>
<style>@keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: .25; } }</style>
@endsection
