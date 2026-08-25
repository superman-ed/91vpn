@extends('layouts.admin')
@section('title', '设备统计')
@section('content')
@php
    $platMeta = [
        'iOS' => ['fab fa-apple', '#111'], 'Android' => ['fab fa-android', '#3ddc84'],
        'Windows' => ['fab fa-windows', '#0078d6'], 'macOS' => ['fab fa-apple', '#555'],
        'Linux' => ['fab fa-linux', '#f5a623'], '其它' => ['fas fa-question', '#98a6ad'], '未知' => ['fas fa-question', '#98a6ad'],
    ];
@endphp
<style>
    .src-head { display:flex; align-items:center; gap:10px; margin:0 0 14px; flex-wrap:wrap; }
    .src-head .bar { width:4px; height:20px; border-radius:3px; }
    .src-head h5 { font-size:15px; font-weight:700; color:#34395e; margin:0; }
    .src-badge { font-size:11px; font-weight:700; padding:3px 9px; border-radius:20px; }
    .src-badge.exact { background:#e9f9ed; color:#2fa84f; }
    .src-badge.est { background:#f2f3f5; color:#98a6ad; }
    .src-desc { font-size:12px; color:#98a6ad; }
</style>

<div class="adm-head">
    <h4><i class="fas fa-mobile-alt text-primary"></i> 设备统计</h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($from || $to)<a href="/admin/system/devices" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="card adm-panel" style="margin-bottom:22px;background:#fafbff">
    <div style="padding:13px 20px;font-size:12.5px;color:#6b7a90;line-height:1.7">
        本页有两个数据来源：<b style="color:#2fa84f">自研客户端（精确）</b>由客户端主动上报，能到具体机型/系统版本/App版本，但仅覆盖装了自研 App 的用户；<b style="color:#7a8896">订阅识别（估算）</b>从订阅拉取的 UA 推断，只能到平台层，但覆盖所有客户端。
    </div>
</div>

{{-- ═══ 区块一：自研客户端（精确） ═══ --}}
<div class="src-head">
    <span class="bar" style="background:#6777ef"></span>
    <h5><i class="fas fa-rocket" style="color:#6777ef"></i> 自研客户端设备</h5>
    <span class="src-badge exact">精确</span>
    @if($deviceCount > 0)<span class="src-desc">{{ $deviceCount }} 台 · {{ $deviceUserCount }} 用户 · <span style="color:#2fa84f">在线 {{ $onlineDevices }}</span></span>@endif
</div>

@if($deviceCount === 0)
<div class="card adm-panel" style="margin-bottom:24px">
    <div style="padding:30px 20px;text-align:center;color:#98a6ad">
        <i class="fas fa-rocket fa-2x mb-2 d-block" style="opacity:.4"></i>
        接收框架已就绪，等自研客户端接入后自动统计精确机型 / 系统版本 / App 版本<br>
        <small>客户端向 <code style="background:#f1f3fb;padding:1px 6px;border-radius:4px">POST /api/device/report</code>（Bearer api_token）上报即可</small>
    </div>
</div>
@else
<div class="card adm-panel" style="margin-bottom:24px">
    <div class="row" style="padding:16px 12px">
        @foreach([['机型 TOP', $byModel], ['系统版本', $byOsVersion], ['App 版本', $byAppVersion]] as [$title, $dist])
        <div class="col-md-4">
            <div style="padding:6px 12px"><div style="font-size:12.5px;color:#98a6ad;font-weight:600;margin-bottom:10px">{{ $title }}</div>
            @php $mx = max(1, $dist->max() ?? 1); @endphp
            @forelse($dist as $k => $v)
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
@endif

{{-- ═══ 区块二：订阅识别（估算） ═══ --}}
<div class="src-head">
    <span class="bar" style="background:#98a6ad"></span>
    <h5><i class="fas fa-cloud-download-alt" style="color:#98a6ad"></i> 订阅识别</h5>
    <span class="src-badge est">估算</span>
    <span class="src-desc">按订阅拉取 UA · 覆盖所有客户端 · {{ $totalUsers }} 用户</span>
</div>

<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0">设备平台分布 <span class="text-muted" style="font-weight:400;font-size:12px">（每用户取最近一次）</span></h4></div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;padding:14px 20px 6px">
        @forelse($byPlatform as $name => $cnt)
        @php $m = $platMeta[$name] ?? ['fas fa-question', '#98a6ad']; $pct = $totalUsers > 0 ? round($cnt / $totalUsers * 100) : 0; @endphp
        <div style="flex:1;min-width:150px;display:flex;align-items:center;gap:13px;background:#fafbff;border:1px solid #eef1f8;border-radius:12px;padding:13px 16px">
            <span style="width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:{{ $m[1] }};color:#fff;font-size:19px"><i class="{{ $m[0] }}"></i></span>
            <div><div style="font-size:19px;font-weight:800;color:#34395e;line-height:1.1">{{ $cnt }} <span style="font-size:12px;font-weight:500;color:#98a6ad">人 · {{ $pct }}%</span></div><div style="font-size:13px;color:#54667a">{{ $name }}</div></div>
        </div>
        @empty
        <div class="adm-empty" style="width:100%"><i class="fas fa-mobile-alt fa-2x mb-2 d-block"></i>暂无设备数据<br><small>用户在客户端导入订阅后，这里会按 UA 识别其设备平台</small></div>
        @endforelse
    </div>
    @if($byPlatform->isNotEmpty())
    <div style="padding:8px 20px 18px">
        @php $maxP = max(1, $byPlatform->max()); @endphp
        @foreach($byPlatform as $name => $cnt)
        @php $m = $platMeta[$name] ?? ['fas fa-question', '#98a6ad']; @endphp
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:9px">
            <div style="width:90px;flex:0 0 90px;font-size:13px;color:#34395e;font-weight:600;text-align:right"><i class="{{ $m[0] }}" style="color:{{ $m[1] }};margin-right:5px"></i>{{ $name }}</div>
            <div style="flex:1;background:#f1f3fb;border-radius:6px;height:20px;overflow:hidden"><div style="height:100%;width:{{ round($cnt / $maxP * 100) }}%;background:{{ $m[1] }};opacity:.85;border-radius:6px;min-width:2px"></div></div>
            <div style="width:82px;flex:0 0 82px;font-size:12.5px;color:#54667a">{{ $cnt }} 人 · {{ $totalUsers > 0 ? round($cnt / $totalUsers * 100) : 0 }}%</div>
        </div>
        @endforeach
    </div>
    @endif
</div>

<div class="card adm-panel">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-list text-primary"></i> 最近拉取记录</h4></div>
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>时间</th><th>用户</th><th>设备平台</th><th>订阅类型</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($recent as $r)
            @php $p = device_platform($r->client); $m = $platMeta[$p] ?? ['fas fa-question', '#98a6ad']; @endphp
            <tr>
                <td class="text-muted">{{ $r->fetched_at?->format('Y-m-d H:i:s') }}</td>
                <td style="color:#34395e;font-weight:600">{{ $r->user?->email ?? '—' }}</td>
                <td><i class="{{ $m[0] }}" style="color:{{ $m[1] }};margin-right:6px"></i>{{ $p }} <span class="text-muted" style="font-size:12px">· {{ \Illuminate\Support\Str::limit($r->client, 32) }}</span></td>
                <td>{{ $r->type ?: '—' }}</td>
                <td style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px">{{ $r->ip ?: '—' }}</td>
            </tr>
            @empty<tr><td colspan="5"><div class="adm-empty">暂无拉取记录</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($recent->hasPages())<div class="adm-foot">{{ $recent->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
