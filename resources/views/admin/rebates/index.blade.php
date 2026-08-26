@extends('layouts.admin')
@section('title', '返佣记录')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-hand-holding-usd text-primary"></i> 返佣记录</h4>
    <form method="GET" class="adm-search adm-tools">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索邮箱（受益人/下线）" style="min-width:190px">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q || $from || $to)<a href="/admin/rebates" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#3aa0c7,#2a86ab)"><div style="font-size:22px;font-weight:800">+¥{{ number_format($sumAmount, 2) }}</div><div style="font-size:12.5px;opacity:.9">累计派发返佣</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ $countAll }}</div><div style="font-size:12.5px;opacity:.9">返佣笔数</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ $earnerCount }}</div><div style="font-size:12.5px;opacity:.9">受益人数</div></div>
</div>

@if($topEarners->isNotEmpty())
<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-trophy text-warning"></i> 返佣榜 TOP5</h4></div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;padding:12px 20px 18px">
        @foreach($topEarners as $i => $e)
        <div style="display:flex;align-items:center;gap:10px;background:#fafbff;border:1px solid #eef1f8;border-radius:11px;padding:9px 15px;min-width:210px">
            <span style="width:22px;height:22px;border-radius:50%;background:{{ ['#f5a623','#9aa5b1','#c98a5e'][$i] ?? '#c3cbd6' }};color:#fff;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center">{{ $i + 1 }}</span>
            <div style="flex:1;min-width:0">
                <div style="color:#34395e;font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $e['user']?->email ?? '已删除用户' }}</div>
                <div class="text-muted" style="font-size:11.5px">{{ $e['cnt'] }} 笔</div>
            </div>
            <div style="color:#2fa84f;font-weight:800;font-size:14px">+¥{{ number_format($e['total'], 2) }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>时间</th><th>受益人（邀请人）</th><th>下线</th><th>返佣金额</th><th>关联订单</th></tr></thead>
            <tbody>
            @forelse($rebates as $r)
            <tr>
                <td class="text-muted">{{ $r->created_at?->format('Y-m-d H:i:s') }}</td>
                <td style="color:#34395e;font-weight:600">{{ $r->user?->email ?? '—' }}</td>
                <td>
                    @if($r->fromUser)
                        <span style="color:#54667a">{{ $r->fromUser->email }}</span>
                        <span class="text-muted" style="font-size:12px"> #{{ $r->from_user_id }}</span>
                    @else <span class="text-muted">—</span>@endif
                </td>
                <td style="font-weight:700;color:#2fa84f">+¥{{ number_format($r->amount, 2) }}</td>
                <td style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12px">
                    @if($r->order)<span style="color:#34395e">{{ $r->order->order_no }}</span>
                    @else <span class="text-muted">充值返佣</span>@endif
                </td>
            </tr>
            @empty<tr><td colspan="5"><div class="adm-empty"><i class="fas fa-hand-holding-usd fa-2x mb-2 d-block"></i>暂无返佣记录</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pager', ['p' => $rebates])
</div>
@endsection
