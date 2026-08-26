@extends('layouts.admin')
@section('title', '操作日志')
@section('content')
@php
    $groups = ['' => '全部'] + \App\Http\Controllers\Admin\AuditLogController::GROUPS;
    $actionName = [
        'user.update' => '更新用户', 'user.ban' => '封禁/解封', 'user.grant' => '开通套餐',
        'user.reset_traffic' => '重置流量', 'user.reset_password' => '重置密码',
        'order.mark_paid' => '标记支付', 'order.cancel' => '取消订单',
        'node.create' => '创建节点', 'node.update' => '更新节点', 'node.delete' => '删除节点',
        'node.regenerate_secret' => '重置密钥', 'setting.update' => '更新设置',
        'email.peek_code' => '代查验证码',
        'plan.create' => '创建套餐', 'plan.update' => '更新套餐', 'plan.delete' => '删除套餐',
        'coupon.create' => '创建优惠券', 'coupon.update' => '更新优惠券', 'coupon.delete' => '删除优惠券',
        'announcement.create' => '发布公告', 'announcement.update' => '更新公告', 'announcement.delete' => '删除公告',
        'admin.create' => '新建管理员', 'admin.grant' => '授予管理员', 'admin.revoke' => '撤销管理员',
        'ticket.reply' => '回复工单', 'ticket.close' => '关闭工单',
        'promo.create' => '创建推广码', 'promo.update' => '更新推广码', 'promo.delete' => '删除推广码',
        'order.export' => '导出订单', 'finance.export' => '导出流水', 'user.export' => '导出用户',
    ];
    $pill = function ($a) {
        if (str_contains($a, '.delete') || str_contains($a, '.revoke') || $a === 'user.ban') return 'danger';
        if (str_starts_with($a, 'admin.')) return 'warn';
        if (str_contains($a, '.export') || $a === 'email.peek_code') return 'muted';
        if (str_starts_with($a, 'setting')) return 'primary';
        return 'info';
    };
@endphp
<div class="adm-head">
    <h4><i class="fas fa-clipboard-list text-primary"></i> 操作日志 <span class="text-muted" style="font-size:13px;font-weight:400">管理员后台操作审计</span></h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="hidden" name="group" value="{{ $group }}">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索描述 / 操作人邮箱" style="min-width:180px">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q || $from || $to)<a href="/admin/system/audit{{ $group ? '?group='.$group : '' }}" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($total) }}</div><div style="font-size:12.5px;opacity:.9">操作记录</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ number_format($todayCount) }}</div><div style="font-size:12.5px;opacity:.9">今日操作</div></div>
</div>

<div class="adm-tools" style="margin-bottom:18px">
    @foreach($groups as $k => $label)
    <a href="/admin/system/audit?group={{ $k }}{{ $q ? '&q='.$q : '' }}" class="btn btn-sm {{ (string) $group === $k ? 'adm-btn' : 'btn-light' }}" style="border-radius:9px">{{ $label }}</a>
    @endforeach
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>时间</th><th>操作人</th><th>动作</th><th>描述</th><th>对象</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
            <tr>
                <td class="text-muted">{{ $l->created_at?->format('Y-m-d H:i:s') }}</td>
                <td style="color:#34395e;font-weight:600">{{ $l->admin?->email ?? '系统' }}</td>
                <td><span class="adm-pill {{ $pill($l->action) }}">{{ $actionName[$l->action] ?? $l->action }}</span></td>
                <td>{{ $l->description }}</td>
                <td class="text-muted" style="font-size:12.5px">@if($l->target_type){{ $l->target_type }} #{{ $l->target_id }}@else—@endif</td>
                <td style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12px">{{ $l->ip ?: '—' }}</td>
            </tr>
            @empty<tr><td colspan="6"><div class="adm-empty"><i class="fas fa-clipboard-list fa-2x mb-2 d-block"></i>暂无操作记录</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pager', ['p' => $logs])
</div>
@endsection
