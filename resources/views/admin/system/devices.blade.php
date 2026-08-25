@extends('layouts.admin')
@section('title', '设备统计')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-mobile-alt text-primary"></i> 设备 / 客户端统计 <span class="text-muted" style="font-size:13px;font-weight:400">按订阅拉取的 User-Agent</span></h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($from || $to)<a href="/admin/system/devices" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($totalUsers) }}</div><div style="font-size:12.5px;opacity:.9">有拉取记录的用户</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#3aa0c7,#2a86ab)"><div style="font-size:22px;font-weight:800">{{ number_format($totalFetches) }}</div><div style="font-size:12.5px;opacity:.9">订阅拉取次数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#7c4ddb,#6636c0)"><div style="font-size:22px;font-weight:800">{{ $byPlatform->count() }}</div><div style="font-size:12.5px;opacity:.9">设备平台数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ $byFamily->count() }}</div><div style="font-size:12.5px;opacity:.9">客户端种类</div></div>
</div>

@php
    $platMeta = [
        'iOS' => ['fab fa-apple', '#111'],
        'Android' => ['fab fa-android', '#3ddc84'],
        'Windows' => ['fab fa-windows', '#0078d6'],
        'macOS' => ['fab fa-apple', '#555'],
        'Linux' => ['fab fa-linux', '#f5a623'],
        '其它' => ['fas fa-question', '#98a6ad'],
        '未知' => ['fas fa-question', '#98a6ad'],
    ];
@endphp
<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-mobile-alt text-primary"></i> 设备 / 平台分布 <span class="text-muted" style="font-weight:400;font-size:12px">（每用户取最近一次，按系统归类）</span></h4></div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;padding:14px 20px 18px">
        @forelse($byPlatform as $name => $cnt)
        @php $m = $platMeta[$name] ?? ['fas fa-question', '#98a6ad']; $pct = $totalUsers > 0 ? round($cnt / $totalUsers * 100) : 0; @endphp
        <div style="flex:1;min-width:150px;display:flex;align-items:center;gap:13px;background:#fafbff;border:1px solid #eef1f8;border-radius:12px;padding:13px 16px">
            <span style="width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:{{ $m[1] }};color:#fff;font-size:19px"><i class="{{ $m[0] }}"></i></span>
            <div>
                <div style="font-size:19px;font-weight:800;color:#34395e;line-height:1.1">{{ $cnt }} <span style="font-size:12px;font-weight:500;color:#98a6ad">人 · {{ $pct }}%</span></div>
                <div style="font-size:13px;color:#54667a">{{ $name }}</div>
            </div>
        </div>
        @empty
        <div class="adm-empty" style="width:100%"><i class="fas fa-mobile-alt fa-2x mb-2 d-block"></i>暂无设备数据</div>
        @endforelse
    </div>
</div>

<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-chart-bar text-primary"></i> 客户端软件分布 <span class="text-muted" style="font-weight:400;font-size:12px">（每用户取最近一次）</span></h4></div>
    <div style="padding:12px 20px 18px">
        @php $maxF = max(1, $byFamily->max() ?? 1); @endphp
        @forelse($byFamily as $name => $cnt)
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
            <div style="width:150px;flex:0 0 150px;font-size:13px;color:#34395e;font-weight:600;text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $name }}</div>
            <div style="flex:1;background:#f1f3fb;border-radius:6px;height:22px;overflow:hidden"><div style="height:100%;width:{{ round($cnt / $maxF * 100) }}%;background:linear-gradient(90deg,#6777ef,#8b98ff);border-radius:6px;min-width:2px"></div></div>
            <div style="width:88px;flex:0 0 88px;font-size:12.5px;color:#54667a">{{ $cnt }} 人 · {{ $totalUsers > 0 ? round($cnt / $totalUsers * 100) : 0 }}%</div>
        </div>
        @empty
        <div class="adm-empty"><i class="fas fa-mobile-alt fa-2x mb-2 d-block"></i>暂无订阅拉取记录<br><small>用户在客户端导入订阅后，这里会统计其客户端类型</small></div>
        @endforelse
    </div>
</div>

<div class="card adm-panel">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-list text-primary"></i> 最近拉取记录</h4></div>
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>时间</th><th>用户</th><th>客户端</th><th>订阅类型</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($recent as $r)
            <tr>
                <td class="text-muted">{{ $r->fetched_at?->format('Y-m-d H:i:s') }}</td>
                <td style="color:#34395e;font-weight:600">{{ $r->user?->email ?? '—' }}</td>
                <td><span class="adm-pill primary">{{ client_family($r->client) }}</span> <span class="text-muted" style="font-size:12px">{{ \Illuminate\Support\Str::limit($r->client, 40) }}</span></td>
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
