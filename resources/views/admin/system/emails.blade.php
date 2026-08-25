@extends('layouts.admin')
@section('title', '邮件记录')
@section('content')
@php
    $tabs = ['' => '全部', 'sent' => '成功', 'failed' => '失败', 'logged' => '仅记录'];
    $statusName = ['sent' => '成功', 'failed' => '失败', 'logged' => '未配SMTP·仅记录'];
    $statusPill = ['sent' => 'ok', 'failed' => 'danger', 'logged' => 'muted'];
    $typeName = ['code' => '验证码', 'notice' => '通知'];
@endphp
<div class="adm-head">
    <h4><i class="fas fa-envelope-open-text text-primary"></i> 邮件发送记录</h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="hidden" name="status" value="{{ $status }}">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索收件邮箱" style="min-width:170px">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q || $from || $to)<a href="/admin/system/emails{{ $status ? '?status='.$status : '' }}" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($counts['all']) }}</div><div style="font-size:12.5px;opacity:.9">发送总数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ number_format($counts['sent']) }}</div><div style="font-size:12.5px;opacity:.9">成功</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#fc544b,#e0402f)"><div style="font-size:22px;font-weight:800">{{ number_format($counts['failed']) }}</div><div style="font-size:12.5px;opacity:.9">失败</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#98a6ad,#7a8892)"><div style="font-size:22px;font-weight:800">{{ number_format($counts['logged']) }}</div><div style="font-size:12.5px;opacity:.9">仅记录（未配SMTP）</div></div>
</div>

<div class="adm-tools" style="margin-bottom:18px">
    @foreach($tabs as $k => $label)
    <a href="/admin/system/emails?status={{ $k }}{{ $q ? '&q='.$q : '' }}{{ $from ? '&from='.$from : '' }}{{ $to ? '&to='.$to : '' }}" class="btn btn-sm {{ (string) $status === $k ? 'adm-btn' : 'btn-light' }}" style="border-radius:9px">{{ $label }} <span style="opacity:.7">{{ $k ? ($counts[$k] ?? 0) : $counts['all'] }}</span></a>
    @endforeach
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>时间</th><th>收件邮箱</th><th>类型</th><th>主题</th><th>状态</th><th>失败原因</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
            <tr>
                <td class="text-muted">{{ $l->created_at?->format('Y-m-d H:i:s') }}</td>
                <td style="color:#34395e;font-weight:600">{{ $l->to_email }}</td>
                <td><span class="adm-pill info">{{ $typeName[$l->type] ?? $l->type }}</span></td>
                <td>{{ $l->subject }}</td>
                <td><span class="adm-pill {{ $statusPill[$l->status] ?? 'muted' }}">{{ $statusName[$l->status] ?? $l->status }}</span></td>
                <td class="text-muted" style="font-size:12px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $l->error }}">{{ $l->error ?: '—' }}</td>
            </tr>
            @empty<tr><td colspan="6"><div class="adm-empty"><i class="fas fa-envelope-open-text fa-2x mb-2 d-block"></i>暂无邮件记录<br><small>注册/找回密码发送验证码后，这里会记录每封邮件的发送结果</small></div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="adm-foot">{{ $logs->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
