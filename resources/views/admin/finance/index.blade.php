@extends('layouts.admin')
@section('title', '资金流水')
@section('content')
@php
    $tabs = ['' => '全部', 'recharge' => '充值', 'consume' => '消费', 'rebate' => '返佣', 'bonus' => '注册奖励'];
    $typeName = ['recharge' => '充值', 'consume' => '消费', 'rebate' => '返佣', 'bonus' => '注册奖励'];
    $typePill = ['recharge' => 'ok', 'consume' => 'warn', 'rebate' => 'info', 'bonus' => 'primary'];
@endphp
<div class="adm-head">
    <h4><i class="fas fa-money-bill-transfer text-primary"></i> 资金流水</h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="hidden" name="type" value="{{ $type }}">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索用户邮箱" style="min-width:170px">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q || $from || $to)<a href="/admin/finance{{ $type ? '?type='.$type : '' }}" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">+¥{{ number_format($sumRecharge, 2) }}</div><div style="font-size:12.5px;opacity:.9">充值入账</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#ffb020,#ff9f1a)"><div style="font-size:22px;font-weight:800">−¥{{ number_format($sumConsume, 2) }}</div><div style="font-size:12.5px;opacity:.9">消费支出</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#3aa0c7,#2a86ab)"><div style="font-size:22px;font-weight:800">+¥{{ number_format($sumRebate, 2) }}</div><div style="font-size:12.5px;opacity:.9">返佣派发</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#7c4ddb,#6636c0)"><div style="font-size:22px;font-weight:800">+¥{{ number_format($sumBonus, 2) }}</div><div style="font-size:12.5px;opacity:.9">注册奖励</div></div>
</div>

<div class="adm-tools" style="margin-bottom:18px">
    @foreach($tabs as $k => $label)
    <a href="/admin/finance?type={{ $k }}{{ $q ? '&q='.$q : '' }}{{ $from ? '&from='.$from : '' }}{{ $to ? '&to='.$to : '' }}" class="btn btn-sm {{ (string) $type === $k ? 'adm-btn' : 'btn-light' }}" style="border-radius:9px">{{ $label }} <span style="opacity:.7">{{ $k ? ($counts[$k] ?? 0) : $counts['all'] }}</span></a>
    @endforeach
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>时间</th><th>用户</th><th>类型</th><th>变动</th><th>变动后余额</th><th>关联订单 / 交易号</th><th>备注</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
            @php $isIn = $l->amount > 0; @endphp
            <tr>
                <td class="text-muted">{{ $l->created_at?->format('Y-m-d H:i:s') }}</td>
                <td style="color:#34395e;font-weight:600">{{ $l->user?->email ?? '—' }}</td>
                <td><span class="adm-pill {{ $typePill[$l->type] ?? 'muted' }}">{{ $typeName[$l->type] ?? $l->type }}</span></td>
                <td style="font-weight:700;color:{{ $isIn ? '#2fa84f' : '#fc544b' }}">{{ $isIn ? '+' : '' }}{{ number_format($l->amount, 2) }}</td>
                <td>¥{{ number_format($l->balance_after, 2) }}</td>
                <td style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12px">
                    @if($l->order)
                        <span style="color:#34395e">{{ $l->order->order_no }}</span>
                        @if($l->order->trade_no)<br><span class="text-muted">交易号 {{ $l->order->trade_no }}</span>@endif
                    @else <span class="text-muted">—</span>@endif
                </td>
                <td class="text-muted">{{ $l->remark }}</td>
            </tr>
            @empty<tr><td colspan="7"><div class="adm-empty"><i class="fas fa-money-bill-transfer fa-2x mb-2 d-block"></i>暂无资金流水</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="adm-foot">{{ $logs->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
