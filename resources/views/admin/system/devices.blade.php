@extends('layouts.admin')
@section('title', '设备统计')
@section('content')
@php
    $platMeta = [
        'ios' => ['iOS', 'fab fa-apple', '#111'], 'android' => ['Android', 'fab fa-android', '#3ddc84'],
        'windows' => ['Windows', 'fab fa-windows', '#0078d6'], 'macos' => ['macOS', 'fab fa-apple', '#555'],
        'linux' => ['Linux', 'fab fa-linux', '#f5a623'],
    ];
    $pm = fn ($p) => $platMeta[$p] ?? [$p ?: '未知', 'fas fa-question', '#98a6ad'];
    $platTabs = ['' => '全部'] + collect($platMeta)->mapWithKeys(fn ($v, $k) => [$k => $v[0]])->all();
@endphp
<div class="adm-head">
    <h4><i class="fas fa-mobile-alt text-primary"></i> 设备统计 <span class="text-muted" style="font-size:13px;font-weight:400">已安装自研客户端的设备</span></h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="hidden" name="platform" value="{{ $platform }}">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索机型 / 邮箱" style="min-width:170px">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q || $from || $to)<a href="/admin/system/devices{{ $platform ? '?platform='.$platform : '' }}" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

@if($deviceCount === 0 && ! $platform && ! $q && ! $from && ! $to)
<div class="card adm-panel">
    <div style="padding:48px 20px;text-align:center;color:#98a6ad">
        <i class="fas fa-mobile-alt fa-3x mb-3 d-block" style="opacity:.35"></i>
        <div style="font-size:15px;color:#7a8896;margin-bottom:6px">暂无已安装设备</div>
        接收框架已就绪，用户安装并登录自研客户端后，这里会统计其真实设备<br>
        <small>客户端向 <code style="background:#f1f3fb;padding:1px 6px;border-radius:4px">POST /api/device/report</code>（Bearer api_token）上报机型 / 系统版本 / App 版本</small>
    </div>
</div>
@else

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($deviceCount) }}</div><div style="font-size:12.5px;opacity:.9">设备总数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#3aa0c7,#2a86ab)"><div style="font-size:22px;font-weight:800">{{ number_format($userCount) }}</div><div style="font-size:12.5px;opacity:.9">安装用户数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#2ec27e,#25a06a)"><div style="font-size:22px;font-weight:800">{{ number_format($onlineCount) }}</div><div style="font-size:12.5px;opacity:.9">当前在线设备</div></div>
</div>

{{-- 平台分布 --}}
<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-mobile-alt text-primary"></i> 平台分布</h4></div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;padding:14px 20px 18px">
        @forelse($byPlatform as $p => $cnt)
        @php [$name, $ic, $col] = $pm($p); $pct = $deviceCount > 0 ? round($cnt / $deviceCount * 100) : 0; @endphp
        <div style="flex:1;min-width:150px;display:flex;align-items:center;gap:13px;background:#fafbff;border:1px solid #eef1f8;border-radius:12px;padding:13px 16px">
            <span style="width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:{{ $col }};color:#fff;font-size:19px"><i class="{{ $ic }}"></i></span>
            <div><div style="font-size:19px;font-weight:800;color:#34395e;line-height:1.1">{{ $cnt }} <span style="font-size:12px;font-weight:500;color:#98a6ad">台 · {{ $pct }}%</span></div><div style="font-size:13px;color:#54667a">{{ $name }}</div></div>
        </div>
        @empty<div class="text-muted" style="padding:8px 0">暂无平台数据</div>@endforelse
    </div>
</div>

{{-- 机型 / 系统版本 / App 版本 --}}
<div class="card adm-panel" style="margin-bottom:18px">
    <div class="row" style="padding:16px 12px">
        @foreach([['机型 TOP', $byModel], ['系统版本', $byOsVersion], ['App 版本', $byAppVersion]] as [$title, $d])
        <div class="col-md-4">
            <div style="padding:6px 14px"><div style="font-size:12.5px;color:#98a6ad;font-weight:600;margin-bottom:10px">{{ $title }}</div>
            @php $mx = max(1, $d->max() ?? 1); @endphp
            @forelse($d as $k => $v)
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                <div style="flex:1;min-width:0"><div style="font-size:12.5px;color:#34395e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $k ?: '未知' }}</div>
                <div style="background:#f1f3fb;border-radius:5px;height:6px;margin-top:2px;overflow:hidden"><div style="height:100%;width:{{ round($v / $mx * 100) }}%;background:#6777ef;border-radius:5px"></div></div></div>
                <span style="font-size:12px;color:#54667a">{{ $v }}</span>
            </div>
            @empty<div class="text-muted" style="font-size:12px">—</div>@endforelse
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- 平台筛选 --}}
<div class="adm-tools" style="margin-bottom:14px">
    @foreach($platTabs as $k => $label)
    <a href="/admin/system/devices?platform={{ $k }}{{ $q ? '&q='.$q : '' }}" class="btn btn-sm {{ (string) $platform === $k ? 'adm-btn' : 'btn-light' }}" style="border-radius:9px">{{ $label }}</a>
    @endforeach
</div>

{{-- 设备明细 --}}
<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>用户</th><th>平台</th><th>机型</th><th>系统版本</th><th>App 版本</th><th>IP</th><th>最后在线</th></tr></thead>
            <tbody>
            @forelse($devices as $d)
            @php [$name, $ic, $col] = $pm($d->platform); $online = $d->last_seen && $d->last_seen->gte(now()->subSeconds(\App\Models\Device::ONLINE_WINDOW)); @endphp
            <tr>
                <td style="color:#34395e;font-weight:600">{{ $d->user?->email ?? '—' }}</td>
                <td><i class="{{ $ic }}" style="color:{{ $col }};margin-right:6px"></i>{{ $name }}</td>
                <td>{{ trim($d->brand.' '.$d->model) ?: '—' }}</td>
                <td>{{ $d->os_version ?: '—' }}</td>
                <td>{{ $d->app_version ?: '—' }}</td>
                <td style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px">{{ $d->ip ?: '—' }}</td>
                <td class="text-muted">@if($online)<span class="adm-pill ok">在线</span>@else {{ $d->last_seen?->diffForHumans() ?? '—' }}@endif</td>
            </tr>
            @empty<tr><td colspan="7"><div class="adm-empty">没有符合条件的设备</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pager', ['p' => $devices])
</div>
@endif
@endsection
