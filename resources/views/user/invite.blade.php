@extends('layouts.user')
@section('title', '邀请返利')
@section('head')
<style>
.inv-hero {
    border: none; border-radius: 14px; overflow: hidden; color: #fff;
    background: linear-gradient(135deg, #6777ef 0%, #5a67e8 55%, #7c4ddb 100%);
    box-shadow: 0 10px 30px rgba(103,119,239,.28);
}
.inv-hero .card-body { padding: 26px 28px; }
.inv-hero .inv-eyebrow { font-size: 13px; opacity: .85; letter-spacing: .5px; }
.inv-hero .inv-title { font-size: 26px; font-weight: 800; margin: 4px 0 2px; }
.inv-hero .inv-title b { color: #ffe27a; }
.inv-code-box { display: flex; align-items: center; gap: 12px; margin: 18px 0 6px; flex-wrap: wrap; }
.inv-code { background: rgba(255,255,255,.16); border: 1px dashed rgba(255,255,255,.5); border-radius: 10px; padding: 8px 18px; font-size: 22px; font-weight: 800; letter-spacing: 3px; }
.inv-link-row { display: flex; gap: 8px; margin-top: 14px; max-width: 620px; }
.inv-link-row input { flex: 1; border: none; border-radius: 9px; padding: 10px 14px; color: #34395e; font-size: 13px; }
.inv-copy { background: #fff; color: #4b56d6; font-weight: 700; border-radius: 9px; border: none; white-space: nowrap; padding: 0 18px; }
.inv-copy:hover { color: #4b56d6; filter: brightness(.97); }
.inv-stats { display: flex; gap: 12px; margin-top: 22px; flex-wrap: wrap; }
.inv-stat { background: rgba(255,255,255,.14); border-radius: 10px; padding: 12px 18px; min-width: 120px; }
.inv-stat .n { font-size: 20px; font-weight: 800; }
.inv-stat .t { font-size: 12px; opacity: .85; }

.inv-steps { display: flex; gap: 16px; margin: 22px 0; flex-wrap: wrap; }
.inv-step { flex: 1; min-width: 200px; background: #fff; border-radius: 14px; padding: 20px 22px; box-shadow: 0 5px 18px rgba(103,119,239,.08); position: relative; }
.inv-step .num { width: 34px; height: 34px; border-radius: 50%; background: #eef0ff; color: #6777ef; font-weight: 800; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
.inv-step h5 { font-size: 15px; font-weight: 700; color: #34395e; margin: 0 0 4px; }
.inv-step p { font-size: 13px; color: #7a869a; margin: 0; }

.inv-panel { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; }
.inv-panel .card-header { border-bottom: 1px solid #f1f3fb; padding: 16px 22px; }
.inv-panel .card-header h4 { font-size: 15px; font-weight: 700; color: #34395e; margin: 0; }
.inv-table { margin: 0; }
.inv-table thead th { border: none; background: #fafbff; color: #98a6ad; font-size: 12px; font-weight: 600; padding: 12px 22px; }
.inv-table tbody td { border-top: 1px solid #f4f6fb; padding: 13px 22px; font-size: 13.5px; color: #54667a; vertical-align: middle; }
.inv-table tbody tr:hover { background: #fafbff; }
.inv-amt { color: #63c76a; font-weight: 700; }
.inv-empty { text-align: center; color: #98a6ad; padding: 34px 0; }
.inv-avatar { width: 28px; height: 28px; border-radius: 50%; background: #eef0ff; color: #6777ef; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; margin-right: 8px; }
</style>
@endsection
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format($n, 2), '0'), '.'); $rate = rebate_rate(); $bonus = signup_bonus(); @endphp
<div class="card inv-hero mb-4">
    <div class="card-body">
        <div class="inv-eyebrow"><i class="fas fa-gift"></i> 邀请返利</div>
        <div class="inv-title">邀请好友，赚 <b>{{ $fmt($rate) }}%</b> 充值返利</div>
        <div style="opacity:.9;font-size:13.5px">好友通过你的链接注册即得 <b>{{ $fmt($bonus) }}</b> 元初始资金；TA 每次充值，你都能拿到充值金额的 <b>{{ $fmt($rate) }}%</b> 返利，直接进钱包余额。</div>

        <div class="inv-code-box">
            <span style="opacity:.85;font-size:13px">我的邀请码</span>
            <span class="inv-code">{{ $user->ref_code }}</span>
        </div>
        <div class="inv-link-row">
            <input type="text" id="inviteUrl" value="{{ $inviteUrl }}" readonly onclick="this.select()">
            <button class="btn inv-copy" id="copyBtn"><i class="fas fa-copy"></i> 复制链接</button>
        </div>

        <div class="inv-stats">
            <div class="inv-stat"><div class="n">{{ $downlines->count() }}</div><div class="t">已邀请人数</div></div>
            <div class="inv-stat"><div class="n">¥{{ number_format($totalPayback, 2) }}</div><div class="t">累计返利</div></div>
            <div class="inv-stat"><div class="n">{{ $fmt($rate) }}%</div><div class="t">充值返利比例</div></div>
        </div>
    </div>
</div>

<div class="inv-steps">
    <div class="inv-step"><div class="num">1</div><h5>分享邀请链接</h5><p>把上方专属链接发给好友。</p></div>
    <div class="inv-step"><div class="num">2</div><h5>好友注册得 {{ $fmt($bonus) }} 元</h5><p>好友通过链接注册即成为你的下线，并获得 {{ $fmt($bonus) }} 元初始资金。</p></div>
    <div class="inv-step"><div class="num">3</div><h5>充值躺赚 {{ $fmt($rate) }}%</h5><p>下线每次充值的 {{ $fmt($rate) }}% 自动进你钱包。</p></div>
</div>

<div class="row">
    <div class="col-12 col-lg-6 mb-4">
        <div class="card inv-panel">
            <div class="card-header"><h4><i class="fas fa-users text-primary"></i> 我的下线</h4></div>
            <div class="table-responsive">
                <table class="table inv-table">
                    <thead><tr><th>用户</th><th>注册时间</th></tr></thead>
                    <tbody>
                    @forelse($downlines as $d)
                    <tr>
                        <td><span class="inv-avatar">{{ mb_strtoupper(mb_substr($d->email, 0, 1)) }}</span>{{ $d->email }}</td>
                        <td class="text-muted">{{ $d->created_at?->format('Y-m-d') }}</td>
                    </tr>
                    @empty<tr><td colspan="2"><div class="inv-empty"><i class="fas fa-user-plus fa-2x mb-2 d-block"></i>还没有邀请任何人</div></td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6 mb-4">
        <div class="card inv-panel">
            <div class="card-header"><h4><i class="fas fa-coins text-primary"></i> 返利记录</h4></div>
            <div class="table-responsive">
                <table class="table inv-table">
                    <thead><tr><th>来自</th><th>金额</th><th>时间</th></tr></thead>
                    <tbody>
                    @forelse($paybacks as $p)
                    <tr>
                        <td>{{ $p->fromUser?->email ?? '—' }}</td>
                        <td class="inv-amt">+¥{{ number_format($p->amount, 2) }}</td>
                        <td class="text-muted">{{ $p->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                    @empty<tr><td colspan="3"><div class="inv-empty"><i class="fas fa-coins fa-2x mb-2 d-block"></i>暂无返利</div></td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('copyBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var input = document.getElementById('inviteUrl');
        input.select();
        var done = function () { btn.innerHTML = '<i class="fas fa-check"></i> 已复制'; setTimeout(function () { btn.innerHTML = '<i class="fas fa-copy"></i> 复制链接'; }, 1800); };
        if (navigator.clipboard) { navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); }); }
        else { document.execCommand('copy'); done(); }
    });
})();
</script>
@endsection
