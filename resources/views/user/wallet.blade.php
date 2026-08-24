@extends('layouts.user')
@section('title', '我的钱包')
@section('head')
<style>
.wallet-hero {
    border: none; border-radius: 14px; overflow: hidden; color: #fff;
    background: linear-gradient(135deg, #6777ef 0%, #5a67e8 60%, #4b56d6 100%);
    box-shadow: 0 10px 30px rgba(103,119,239,.28);
}
.wallet-hero .card-body { padding: 24px 26px; }
.wallet-hero .wh-label { font-size: 13px; opacity: .85; letter-spacing: .5px; }
.wallet-hero .wh-balance { font-size: 40px; font-weight: 800; line-height: 1.1; margin: 2px 0 2px; }
.wallet-hero .wh-balance small { font-size: 20px; font-weight: 700; opacity: .9; }
.wh-stats { display: flex; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
.wh-stat { flex: 1; min-width: 100px; background: rgba(255,255,255,.14); border-radius: 10px; padding: 10px 12px; }
.wh-stat .n { font-size: 18px; font-weight: 700; }
.wh-stat .t { font-size: 12px; opacity: .85; }
.wh-actions { margin-top: 20px; }
.wh-actions .btn-white { background: #fff; color: #4b56d6; font-weight: 700; border-radius: 9px; }
.wh-actions .btn-white:hover { color: #4b56d6; filter: brightness(.97); }

.recharge-card { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.10); height: 100%; }
.recharge-card .card-body { padding: 22px 24px; }
.rc-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.rc-chip {
    flex: 1; min-width: 60px; text-align: center; padding: 9px 0; border: 1.5px solid #eef0f5;
    border-radius: 9px; cursor: pointer; font-weight: 700; color: #34395e; transition: all .15s; background: #fff;
}
.rc-chip:hover { border-color: #c9d0f7; }
.rc-chip.active { border-color: #6777ef; background: #f5f6ff; color: #6777ef; }
.rc-input { border-radius: 9px; }
.rc-btn { border-radius: 9px; font-weight: 700; background: linear-gradient(135deg,#6777ef,#5a67e8); border: none; }
.rc-btn:hover { filter: brightness(1.05); }

.wallet-panel { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; }
.wallet-panel .card-header { border-bottom: 1px solid #f1f3fb; padding: 16px 22px; }
.wallet-panel .card-header h4 { font-size: 15px; font-weight: 700; color: #34395e; margin: 0; }
.wallet-table { margin: 0; }
.wallet-table thead th { border: none; background: #fafbff; color: #98a6ad; font-size: 12px; font-weight: 600; text-transform: none; padding: 12px 22px; }
.wallet-table tbody td { border-top: 1px solid #f4f6fb; padding: 14px 22px; vertical-align: middle; font-size: 13.5px; color: #54667a; }
.wallet-table tbody tr:hover { background: #fafbff; }
.amt-plus { color: #63c76a; font-weight: 700; }
.amt-minus { color: #fc544b; font-weight: 700; }
.flow-ic { width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; margin-right: 8px; }
.flow-ic.in { background: #63c76a; } .flow-ic.out { background: #fc544b; }
.wallet-empty { text-align: center; color: #98a6ad; padding: 38px 0; }
.badge-pill-soft { padding: 5px 11px; border-radius: 20px; font-weight: 600; font-size: 12px; }
</style>
@endsection
@section('content')
<div class="row">
    <div class="col-12 col-lg-8 mb-4">
        <div class="card wallet-hero">
            <div class="card-body">
                <div class="wh-label"><i class="fas fa-wallet"></i> 钱包余额</div>
                <div class="wh-balance"><small>¥</small>{{ number_format($user->money, 2) }}</div>
                <div class="wh-stats">
                    <div class="wh-stat"><div class="n">¥{{ number_format($totalRecharge, 2) }}</div><div class="t">累计充值</div></div>
                    <div class="wh-stat"><div class="n">¥{{ number_format($totalConsume, 2) }}</div><div class="t">累计消费</div></div>
                    <div class="wh-stat"><div class="n">¥{{ number_format($totalRebate, 2) }}</div><div class="t">累计返利</div></div>
                </div>
                <div class="wh-actions">
                    <a href="/user/shop" class="btn btn-white"><i class="fas fa-store"></i> 购买套餐</a>
                    <a href="/user/invite" class="btn btn-link text-white" style="opacity:.9"><i class="fas fa-gift"></i> 邀请返利</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4 mb-4">
        <div class="card recharge-card">
            <div class="card-header" style="border:none;padding-bottom:0"><h4 style="font-size:15px;font-weight:700;color:#34395e">充值</h4></div>
            <div class="card-body">
                <form method="POST" action="/user/wallet/recharge" id="rechargeForm">@csrf
                    <div class="rc-chips">
                        @foreach([10, 30, 50, 100] as $amt)
                        <div class="rc-chip" data-amt="{{ $amt }}">{{ $amt }}</div>
                        @endforeach
                    </div>
                    <div class="input-group mb-2">
                        <div class="input-group-prepend"><span class="input-group-text">¥</span></div>
                        <input type="number" name="amount" min="1" step="1" class="form-control rc-input" placeholder="自定义金额" required>
                    </div>
                    <button class="btn rc-btn btn-block text-white"><i class="fas fa-bolt"></i> 立即充值</button>
                </form>
                <small class="text-muted d-block mt-2">开发环境模拟充值，提交即到账。</small>
            </div>
        </div>
    </div>
</div>

<div class="card wallet-panel mb-4">
    <div class="card-header"><h4><i class="fas fa-shopping-bag text-primary"></i> 购买记录</h4></div>
    <div class="table-responsive">
        <table class="table wallet-table">
            <thead><tr><th>商品</th><th>金额</th><th>状态</th><th>操作</th><th>时间</th></tr></thead>
            <tbody>
            @forelse($orders as $o)
            <tr>
                <td style="color:#34395e;font-weight:600">{{ $o->plan?->name ?? '—' }}</td>
                <td>¥{{ number_format($o->amount, 2) }}</td>
                <td>
                    @switch($o->status)
                        @case('paid')<span class="badge badge-pill-soft" style="background:#e9f9ed;color:#2fa84f">已支付</span>@break
                        @case('queued')<span class="badge badge-pill-soft" style="background:#e7f3ff;color:#3a8ee6">排队中</span>@break
                        @case('pending')<span class="badge badge-pill-soft" style="background:#fff5e6;color:#e6912a">待支付</span>@break
                        @default<span class="badge badge-pill-soft" style="background:#f2f3f5;color:#98a6ad">已取消</span>
                    @endswitch
                </td>
                <td>
                    @if($o->status === 'pending')
                        <a href="/user/order/{{ $o->id }}" class="btn btn-primary btn-sm" style="border-radius:8px">去支付</a>
                        <form method="POST" action="/user/order/{{ $o->id }}/cancel" class="d-inline" onsubmit="return confirm('确定取消该订单？')">@csrf<button class="btn btn-outline-secondary btn-sm" style="border-radius:8px">取消</button></form>
                    @elseif($o->status === 'queued')
                        <span class="text-muted">预计 {{ $o->activate_at?->format('m-d H:i') }} 生效</span>
                    @else — @endif
                </td>
                <td class="text-muted">{{ $o->created_at?->format('Y-m-d H:i') }}</td>
            </tr>
            @empty<tr><td colspan="5"><div class="wallet-empty"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>暂无购买记录</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card wallet-panel">
    <div class="card-header"><h4><i class="fas fa-exchange-alt text-primary"></i> 余额流水</h4></div>
    <div class="table-responsive">
        <table class="table wallet-table">
            <thead><tr><th>类型</th><th>变动</th><th>变动后</th><th>备注</th><th>时间</th></tr></thead>
            <tbody>
            @forelse($balanceLogs as $l)
            @php $isIn = $l->amount > 0; @endphp
            <tr>
                <td><span class="flow-ic {{ $isIn ? 'in' : 'out' }}"><i class="fas fa-{{ $isIn ? 'arrow-down' : 'arrow-up' }}"></i></span>{{ $l->type === 'recharge' ? '充值' : '消费' }}</td>
                <td class="{{ $isIn ? 'amt-plus' : 'amt-minus' }}">{{ $isIn ? '+' : '' }}{{ number_format($l->amount, 2) }}</td>
                <td style="color:#34395e;font-weight:600">¥{{ number_format($l->balance_after, 2) }}</td>
                <td>{{ $l->remark }}</td>
                <td class="text-muted">{{ $l->created_at?->format('Y-m-d H:i') }}</td>
            </tr>
            @empty<tr><td colspan="5"><div class="wallet-empty"><i class="fas fa-receipt fa-2x mb-2 d-block"></i>暂无余额流水</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
document.querySelectorAll('.rc-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
        document.querySelectorAll('.rc-chip').forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        var input = document.querySelector('#rechargeForm input[name="amount"]');
        if (input) input.value = chip.dataset.amt;
    });
});
</script>
@endsection
