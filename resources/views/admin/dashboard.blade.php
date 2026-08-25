@extends('layouts.admin')
@section('title', '概览')
@section('content')
<style>
.ad-stats { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.ad-stat { flex: 1; min-width: 190px; border-radius: 14px; padding: 20px 22px; color: #fff; position: relative; overflow: hidden; }
.ad-stat.a { background: linear-gradient(135deg,#6777ef,#5a67e8); box-shadow: 0 8px 22px rgba(103,119,239,.22); }
.ad-stat.b { background: linear-gradient(135deg,#63c76a,#3fae57); box-shadow: 0 8px 22px rgba(99,199,106,.22); }
.ad-stat.c { background: linear-gradient(135deg,#ffb020,#ff9f1a); box-shadow: 0 8px 22px rgba(255,160,20,.22); }
.ad-stat.d { background: linear-gradient(135deg,#7c4ddb,#6636c0); box-shadow: 0 8px 22px rgba(124,77,219,.22); }
.ad-stat .ic { position: absolute; right: 16px; top: 16px; font-size: 34px; opacity: .22; }
.ad-stat .n { font-size: 28px; font-weight: 800; line-height: 1.1; }
.ad-stat .t { font-size: 13px; opacity: .9; margin-top: 4px; }
.ad-stat .sub { font-size: 12px; opacity: .85; margin-top: 8px; }
.ad-mini { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.ad-minicard { flex: 1; min-width: 180px; display: flex; align-items: center; gap: 12px; background: #fff; border-radius: 13px; padding: 15px 18px; box-shadow: 0 4px 14px rgba(103,119,239,.07); }
.ad-minicard .mi { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
.ad-minicard .n { font-size: 20px; font-weight: 800; color: #34395e; line-height: 1.1; }
.ad-minicard .t { font-size: 12.5px; color: #7a869a; }

.ad-card { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; margin-bottom: 20px; }
.ad-card .card-header { border-bottom: 1px solid #f1f3fb; padding: 15px 22px; }
.ad-card .card-header h4 { font-size: 15px; font-weight: 700; color: #34395e; margin: 0; }
.ad-card .card-body { padding: 20px 22px; }

.ad-todo { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.ad-todo a { flex: 1; min-width: 220px; display: flex; align-items: center; gap: 14px; background: #fff; border-radius: 13px; padding: 16px 20px; box-shadow: 0 4px 14px rgba(103,119,239,.07); text-decoration: none; transition: transform .15s; }
.ad-todo a:hover { transform: translateY(-2px); text-decoration: none; }
.ad-todo .tic { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff; flex-shrink: 0; }
.ad-todo .cnt { font-size: 22px; font-weight: 800; color: #34395e; }
.ad-todo .lbl { font-size: 13px; color: #7a869a; }
.ad-todo .badge-n { margin-left: auto; background: #fc544b; color: #fff; border-radius: 20px; padding: 2px 10px; font-size: 12px; font-weight: 700; }

.ad-chart { display: flex; align-items: flex-end; gap: 6px; height: 150px; }
.ad-bar { flex: 1; min-width: 6px; background: linear-gradient(180deg,#8b98f5,#6777ef); border-radius: 4px 4px 0 0; min-height: 3px; }
.ad-bar.zero { background: #eef0f5; }
.ad-axis { display: flex; justify-content: space-between; margin-top: 8px; font-size: 11px; color: #98a6ad; }

.ad-table { margin: 0; }
.ad-table thead th { border: none; background: #fafbff; color: #98a6ad; font-size: 12px; font-weight: 600; padding: 11px 22px; }
.ad-table tbody td { border-top: 1px solid #f4f6fb; padding: 12px 22px; font-size: 13.5px; color: #54667a; vertical-align: middle; }
.ad-quick .btn { border-radius: 9px; font-weight: 600; margin: 0 6px 6px 0; }
</style>

<div class="ad-stats">
    <div class="ad-stat a"><i class="fas fa-users ic"></i><div class="n">{{ number_format($userCount) }}</div><div class="t">用户总数</div><div class="sub">今日新增 +{{ $todayUsers }}</div></div>
    <div class="ad-stat b"><i class="fas fa-yen-sign ic"></i><div class="n">¥{{ number_format($revenue, 2) }}</div><div class="t">累计收入</div><div class="sub">今日 +¥{{ number_format($todayRevenue, 2) }}</div></div>
    <div class="ad-stat c"><i class="fas fa-cart-shopping ic"></i><div class="n">{{ number_format($paidOrders) }}</div><div class="t">已支付订单</div><div class="sub">今日 +{{ $todayOrders }} 单</div></div>
    <div class="ad-stat d"><i class="fas fa-server ic"></i><div class="n">{{ $onlineNodes }}/{{ $nodeCount }}</div><div class="t">在线节点</div><div class="sub">共 {{ $planCount }} 个套餐</div></div>
</div>

<div class="ad-mini">
    <div class="ad-minicard"><span class="mi" style="background:#e9f9ed;color:#3fae57"><i class="fas fa-yen-sign"></i></span><span><div class="n">¥{{ number_format($todayRevenue, 2) }}</div><div class="t">今日收入</div></span></div>
    <div class="ad-minicard"><span class="mi" style="background:#eef0ff;color:#6777ef"><i class="fas fa-cart-shopping"></i></span><span><div class="n">{{ $todayOrders }}</div><div class="t">今日订单</div></span></div>
    <div class="ad-minicard"><span class="mi" style="background:#f3ecff;color:#7c4ddb"><i class="fas fa-user-plus"></i></span><span><div class="n">{{ $todayUsers }}</div><div class="t">今日新增用户</div></span></div>
</div>

<div class="ad-todo">
    <a href="/admin/tickets">
        <span class="tic" style="background:linear-gradient(135deg,#6777ef,#5a67e8)"><i class="fas fa-headset"></i></span>
        <span><div class="cnt">{{ $openTickets }}</div><div class="lbl">待处理工单</div></span>
        @if($openTickets > 0)<span class="badge-n">待处理</span>@endif
    </a>
    <a href="/admin/orders">
        <span class="tic" style="background:linear-gradient(135deg,#ffb020,#ff9f1a)"><i class="fas fa-clock"></i></span>
        <span><div class="cnt">{{ $pendingOrders }}</div><div class="lbl">待支付订单</div></span>
        @if($pendingOrders > 0)<span class="badge-n" style="background:#ffb020">待支付</span>@endif
    </a>
</div>

@php
    $presets = [
        '今日' => [now()->toDateString(), now()->toDateString()],
        '近7天' => [now()->subDays(6)->toDateString(), now()->toDateString()],
        '近30天' => [now()->subDays(29)->toDateString(), now()->toDateString()],
        '本月' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
    ];
@endphp
<div class="card ad-card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="gap:10px">
        <h4><i class="fas fa-chart-column text-primary"></i> 收入趋势</h4>
        <form method="GET" class="d-flex align-items-center flex-wrap" style="gap:6px">
            <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm" style="width:auto;border-radius:8px">
            <span class="text-muted">~</span>
            <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm" style="width:auto;border-radius:8px">
            <button class="btn btn-sm adm-btn">查询</button>
            @foreach($presets as $label => $range)
            <a href="/admin?from={{ $range[0] }}&to={{ $range[1] }}" class="btn btn-sm btn-light" style="border-radius:8px">{{ $label }}</a>
            @endforeach
        </form>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap mb-3" style="gap:24px">
            <div><div style="font-size:20px;font-weight:800;color:#3fae57">¥{{ number_format($rangeRevenue, 2) }}</div><div class="text-muted" style="font-size:12px">区间收入</div></div>
            <div><div style="font-size:20px;font-weight:800;color:#6777ef">{{ $rangeOrders }}</div><div class="text-muted" style="font-size:12px">区间订单</div></div>
            <div><div style="font-size:20px;font-weight:800;color:#7c4ddb">{{ $rangeUsers }}</div><div class="text-muted" style="font-size:12px">区间新增用户</div></div>
        </div>
        <div class="ad-chart">
            @foreach($chart as $c)
            <div class="ad-bar {{ $c['value'] == 0 ? 'zero' : '' }}" style="height:{{ max(3, round($c['value'] / $chartMax * 100)) }}%" title="{{ $c['label'] }}：¥{{ number_format($c['value'], 2) }}"></div>
            @endforeach
        </div>
        <div class="ad-axis"><span>{{ $chart->first()['label'] }}</span><span>{{ $chart->last()['label'] }}</span></div>
    </div>
</div>

<div class="card ad-card">
    <div class="card-header"><h4><i class="fas fa-receipt text-primary"></i> 最近订单</h4></div>
    <div class="table-responsive">
        <table class="table ad-table">
            <thead><tr><th>用户</th><th>套餐</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
            <tbody>
            @forelse($recentOrders as $o)
            <tr>
                <td style="color:#34395e;font-weight:600">{{ $o->user?->email ?? '—' }}</td>
                <td>{{ $o->plan?->name ?? '—' }}</td>
                <td>¥{{ number_format($o->amount, 2) }}</td>
                <td>
                    @switch($o->status)
                        @case('paid')<span class="badge badge-success">已支付</span>@break
                        @case('queued')<span class="badge badge-info">排队中</span>@break
                        @case('pending')<span class="badge badge-warning">待支付</span>@break
                        @default<span class="badge badge-secondary">已取消</span>
                    @endswitch
                </td>
                <td class="text-muted">{{ $o->created_at?->format('Y-m-d H:i') }}</td>
            </tr>
            @empty<tr><td colspan="5" class="text-center text-muted py-4">暂无订单</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card ad-card">
    <div class="card-header"><h4><i class="fas fa-bolt text-primary"></i> 快捷操作</h4></div>
    <div class="card-body ad-quick">
        <a href="/admin/nodes/create" class="btn btn-primary"><i class="fas fa-server"></i> 添加节点</a>
        <a href="/admin/plans/create" class="btn btn-outline-primary"><i class="fas fa-box"></i> 添加套餐</a>
        <a href="/admin/coupons/create" class="btn btn-outline-primary"><i class="fas fa-ticket"></i> 生成优惠券</a>
        <a href="/admin/announcements/create" class="btn btn-outline-primary"><i class="fas fa-bullhorn"></i> 发布公告</a>
        <a href="/admin/settings" class="btn btn-outline-primary"><i class="fas fa-cog"></i> 站点设置</a>
    </div>
</div>
@endsection
