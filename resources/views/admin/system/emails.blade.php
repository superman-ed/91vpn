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

{{-- 验证码代查：实时读缓存(5分钟有效)，不落库，每次代查记审计 --}}
<div class="card adm-panel" style="margin-bottom:18px">
    <div style="padding:16px 20px">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-weight:700;color:#34395e"><i class="fas fa-key text-warning"></i> 验证码代查</span>
            <form method="GET" class="d-flex" style="gap:6px;flex-wrap:wrap;margin:0">
                <input type="hidden" name="status" value="{{ $status }}">
                <input name="peek" value="{{ $peekEmail }}" class="form-control" placeholder="输入用户邮箱" style="min-width:220px;border-radius:9px">
                <button class="btn adm-btn" style="border-radius:9px"><i class="fas fa-search"></i> 查当前验证码</button>
            </form>
            @if($peekDone)
                @if($peekCode)
                <span style="display:inline-flex;align-items:center;gap:8px;background:#e9f9ed;border:1px solid #bce8c8;border-radius:9px;padding:7px 14px">
                    <span class="text-muted" style="font-size:12.5px">{{ $peekEmail }} 当前验证码</span>
                    <b style="font-size:18px;letter-spacing:3px;color:#2fa84f;font-family:SFMono-Regular,Menlo,Consolas,monospace">{{ $peekCode }}</b>
                </span>
                @else
                <span style="background:#fdecea;border:1px solid #f5c6c2;border-radius:9px;padding:8px 14px;color:#c9392f;font-size:13px">无有效验证码（未申请、已过期或已使用）</span>
                @endif
            @endif
        </div>
        <div class="text-muted" style="font-size:12px;margin-top:8px">仅能查到 5 分钟有效期内、尚未使用的验证码；每次代查都会记入「操作日志」。用户收不到邮件时可代查后口头/其它渠道告知本人。</div>
    </div>
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
    @include('admin.partials.pager', ['p' => $logs])
</div>
@endsection
