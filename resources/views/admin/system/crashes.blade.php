@extends('layouts.admin')
@section('title', '崩溃日志')
@section('content')
@php
    $platLabel = ['ios' => 'iOS', 'android' => 'Android', 'windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux'];
@endphp
<div class="adm-head">
    <h4><i class="fas fa-bug text-primary"></i> 崩溃日志 <span class="text-muted" style="font-size:13px;font-weight:400">自研客户端 JS 层错误(按 bug 聚合)</span></h4>
    <form method="GET" class="adm-search adm-tools">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索错误信息" style="min-width:180px">
        <input name="app_version" value="{{ $appVersion }}" class="form-control" placeholder="App 版本" style="width:120px">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q || $appVersion || $platform)<a href="/admin/system/crashes" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

@if($total === 0 && ! $q && ! $appVersion && ! $platform)
<div class="card adm-panel">
    <div style="padding:48px 20px;text-align:center;color:#98a6ad">
        <i class="fas fa-bug fa-3x mb-3 d-block" style="opacity:.35"></i>
        <div style="font-size:15px;color:#7a8896;margin-bottom:6px">暂无崩溃记录</div>
        客户端出现未捕获错误时,会向 <code style="background:#f1f3fb;padding:1px 6px;border-radius:4px">POST /api/crash</code> 上报,这里按 bug 聚合展示<br>
        <small>自建方案,不依赖第三方(Sentry);游客崩溃也会上报,归属用户可为空</small>
    </div>
</div>
@else

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#ef6767,#d04d4d)"><div style="font-size:22px;font-weight:800">{{ number_format($total) }}</div><div style="font-size:12.5px;opacity:.9">崩溃总次数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#ef9a67,#d0774d)"><div style="font-size:22px;font-weight:800">{{ number_format($last24h) }}</div><div style="font-size:12.5px;opacity:.9">近 24 小时</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($kinds) }}</div><div style="font-size:12.5px;opacity:.9">不同 bug 数</div></div>
</div>

@if($byAppVersion->isNotEmpty())
<div class="card adm-panel" style="margin-bottom:18px">
    <div style="display:flex;flex-wrap:wrap;gap:10px;padding:14px 20px">
        <span class="text-muted" style="font-size:13px;align-self:center">按 App 版本:</span>
        @foreach($byAppVersion as $ver => $c)
            <span class="badge" style="background:#f1f3fb;color:#34395e;border-radius:8px;padding:6px 10px;font-size:12.5px">v{{ $ver ?: '—' }} · {{ $c }}</span>
        @endforeach
    </div>
</div>
@endif

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table" style="margin:0">
            <thead><tr><th>错误</th><th style="width:90px">平台</th><th style="width:90px">App</th><th style="width:70px">次数</th><th style="width:70px">用户</th><th style="width:150px">最近发生</th></tr></thead>
            <tbody>
            @forelse($groups as $g)
                <tr>
                    <td><a href="/admin/system/crashes/{{ $g->fingerprint }}" style="color:#34395e;font-weight:600">{{ \Illuminate\Support\Str::limit($g->message, 90) ?: '(空)' }}</a></td>
                    <td>{{ $platLabel[$g->platform] ?? ($g->platform ?: '—') }}</td>
                    <td>v{{ $g->app_version ?: '—' }}</td>
                    <td><span class="badge" style="background:#fdecec;color:#d04d4d;border-radius:7px;padding:4px 8px">{{ $g->c }}</span></td>
                    <td>{{ $g->users }}</td>
                    <td class="text-muted" style="font-size:12.5px">{{ \Illuminate\Support\Carbon::parse($g->last_at)->format('m-d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#98a6ad;padding:30px">没有匹配的崩溃记录</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="margin-top:16px">{{ $groups->links() }}</div>
@endif
@endsection
