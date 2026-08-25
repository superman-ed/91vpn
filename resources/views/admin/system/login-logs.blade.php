@extends('layouts.admin')
@section('title', '登录日志')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-sign-in-alt text-primary"></i> 登录日志</h4>
    <form method="GET" class="adm-search adm-tools">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索邮箱 / IP" style="min-width:170px">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q || $from || $to)<a href="/admin/system/login-logs" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($total) }}</div><div style="font-size:12.5px;opacity:.9">登录次数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ number_format($todayCount) }}</div><div style="font-size:12.5px;opacity:.9">今日登录</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#3aa0c7,#2a86ab)"><div style="font-size:22px;font-weight:800">{{ number_format($uniqueIps) }}</div><div style="font-size:12.5px;opacity:.9">独立 IP</div></div>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>时间</th><th>用户</th><th>IP</th><th>地点</th><th>客户端</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
            <tr>
                <td class="text-muted">{{ $l->logged_at?->format('Y-m-d H:i:s') }}</td>
                <td style="color:#34395e;font-weight:600">{{ $l->user?->email ?? '—' }}</td>
                <td style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px">{{ $l->ip ?: '—' }}</td>
                <td>{{ $l->location ?: '—' }}</td>
                <td class="text-muted" style="font-size:12.5px;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $l->user_agent }}">
                    <span class="adm-pill muted" style="margin-right:6px">{{ client_family($l->user_agent) }}</span>{{ $l->user_agent ?: '—' }}
                </td>
            </tr>
            @empty<tr><td colspan="5"><div class="adm-empty"><i class="fas fa-sign-in-alt fa-2x mb-2 d-block"></i>暂无登录记录</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="adm-foot">{{ $logs->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
