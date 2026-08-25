@extends('layouts.admin')
@section('title', '设备统计')
@section('content')
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
    $top = $byPlatform->keys()->first();
@endphp
<div class="adm-head">
    <h4><i class="fas fa-mobile-alt text-primary"></i> 设备统计 <span class="text-muted" style="font-size:13px;font-weight:400">按订阅拉取识别用户设备平台</span></h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($from || $to)<a href="/admin/system/devices" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($totalUsers) }}</div><div style="font-size:12.5px;opacity:.9">统计用户数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#7c4ddb,#6636c0)"><div style="font-size:22px;font-weight:800">{{ $byPlatform->count() }}</div><div style="font-size:12.5px;opacity:.9">覆盖平台数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ $top ?? '—' }}</div><div style="font-size:12.5px;opacity:.9">占比最高平台</div></div>
</div>

<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-mobile-alt text-primary"></i> 设备平台分布 <span class="text-muted" style="font-weight:400;font-size:12px">（每用户取最近一次，做客户端开发优先级参考）</span></h4></div>
    {{-- 图标卡片概览 --}}
    <div style="display:flex;flex-wrap:wrap;gap:12px;padding:14px 20px 6px">
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
        <div class="adm-empty" style="width:100%"><i class="fas fa-mobile-alt fa-2x mb-2 d-block"></i>暂无设备数据<br><small>用户在客户端导入订阅后，这里会按 UA 识别其设备平台</small></div>
        @endforelse
    </div>
    {{-- 占比条 --}}
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
