@extends('layouts.admin')
@section('title', '登录日志')
@section('content')
@php
    $tabs = ['' => '全部', 'success' => '成功', 'failed' => '失败'];
@endphp
<div class="adm-head">
    <h4><i class="fas fa-sign-in-alt text-primary"></i> 登录日志</h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="hidden" name="status" value="{{ $status }}">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索邮箱 / IP" style="min-width:170px">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q || $from || $to)<a href="/admin/system/login-logs{{ $status ? '?status='.$status : '' }}" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

@if($alerts->isNotEmpty())
<div class="card adm-panel" style="margin-bottom:18px;border-left:4px solid #fc544b">
    <div style="padding:14px 20px">
        <div style="font-weight:700;color:#c9392f;margin-bottom:8px"><i class="fas fa-exclamation-triangle"></i> 暴破告警 —— 近 {{ $bfWindow }} 小时内失败 ≥ {{ $bfThreshold }} 次的 IP（{{ $alerts->count() }} 个）</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            @foreach($alerts as $a)
            <a href="/admin/system/login-logs?q={{ $a->ip }}&status=failed" style="display:inline-flex;align-items:center;gap:8px;background:#fdecea;border:1px solid #f5c6c2;border-radius:9px;padding:7px 13px;text-decoration:none">
                <span style="font-family:SFMono-Regular,Menlo,Consolas,monospace;color:#c9392f;font-weight:700">{{ $a->ip }}</span>
                <span style="color:#8a5a56;font-size:12px">失败 {{ $a->fails }} 次 · {{ $a->targets }} 个账号 · 最近 {{ \Illuminate\Support\Carbon::parse($a->last_at)->diffForHumans() }}</span>
            </a>
            @endforeach
        </div>
        <div class="text-muted" style="font-size:12px;margin-top:8px">建议：确认非本人操作后，可在「用户管理」封禁被攻击账号，或在服务器/防火墙层封禁来源 IP。</div>
    </div>
</div>
@endif

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($counts['all']) }}</div><div style="font-size:12.5px;opacity:.9">登录尝试</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ number_format($counts['success']) }}</div><div style="font-size:12.5px;opacity:.9">成功</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#fc544b,#e0402f)"><div style="font-size:22px;font-weight:800">{{ number_format($counts['failed']) }}</div><div style="font-size:12.5px;opacity:.9">失败</div></div>
</div>

<div class="adm-tools" style="margin-bottom:18px">
    @foreach($tabs as $k => $label)
    <a href="/admin/system/login-logs?status={{ $k }}{{ $q ? '&q='.$q : '' }}{{ $from ? '&from='.$from : '' }}{{ $to ? '&to='.$to : '' }}" class="btn btn-sm {{ (string) $status === $k ? 'adm-btn' : 'btn-light' }}" style="border-radius:9px">{{ $label }} <span style="opacity:.7">{{ $k ? ($counts[$k] ?? 0) : $counts['all'] }}</span></a>
    @endforeach
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>时间</th><th>结果</th><th>账号</th><th>IP</th><th>地点</th><th>客户端</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
            <tr>
                <td class="text-muted">{{ $l->logged_at?->format('Y-m-d H:i:s') }}</td>
                <td>@if($l->status === 'success')<span class="adm-pill ok">成功</span>@else<span class="adm-pill danger" title="{{ $l->reason }}">失败</span>@endif</td>
                <td style="color:#34395e;font-weight:600">{{ $l->user?->email ?? $l->email ?: '—' }}</td>
                <td style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px">{{ $l->ip ?: '—' }}</td>
                <td>{{ $l->location ?: '—' }}</td>
                <td class="text-muted" style="font-size:12.5px;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $l->user_agent }}">
                    <span class="adm-pill muted" style="margin-right:6px">{{ client_family($l->user_agent) }}</span>{{ $l->user_agent ?: '—' }}
                </td>
            </tr>
            @empty<tr><td colspan="6"><div class="adm-empty"><i class="fas fa-sign-in-alt fa-2x mb-2 d-block"></i>暂无登录记录</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="adm-foot">{{ $logs->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
