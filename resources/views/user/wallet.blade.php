@extends('layouts.user')
@section('title', '我的钱包')
@section('content')
<div class="cards">
    <div class="card">
        <div class="k">钱包余额</div>
        <div class="v"><small>¥</small> {{ number_format($user->money, 2) }}</div>
    </div>
    <div class="card">
        <div class="k">模拟充值</div>
        <form method="POST" action="/user/wallet/recharge" style="margin-top:10px">
            @csrf
            <input type="number" name="amount" min="1" step="1" placeholder="金额" style="width:100px">
            <button class="btn">充值</button>
        </form>
        <div class="sub">开发环境模拟，直接到账</div>
    </div>
</div>

<div class="panel">
    <h3>购买记录</h3>
    <table>
        <tr><th>商品</th><th>金额</th><th>状态</th><th>操作</th><th>时间</th></tr>
        @forelse ($orders as $o)
        <tr>
            <td>{{ $o->plan?->name ?? '—' }}</td>
            <td>¥{{ number_format($o->amount, 2) }}</td>
            <td>{{ ['pending'=>'待支付','paid'=>'已支付','cancelled'=>'已取消'][$o->status] ?? $o->status }}</td>
            <td>
                @if ($o->status === 'pending')
                    <form method="POST" action="/user/order/{{ $o->id }}/pay-balance" style="display:inline">@csrf<button class="btn ghost" style="padding:4px 10px">余额支付</button></form>
                    <form method="POST" action="/user/order/{{ $o->id }}/mock-pay" style="display:inline">@csrf<button class="btn" style="padding:4px 10px">模拟付款</button></form>
                @else — @endif
            </td>
            <td>{{ $o->created_at?->format('Y-m-d H:i') }}</td>
        </tr>
        @empty
        <tr><td colspan="5" style="color:#acb5c9">暂无订单</td></tr>
        @endforelse
    </table>
</div>

<div class="panel">
    <h3>余额流水</h3>
    <table>
        <tr><th>类型</th><th>变动</th><th>变动后</th><th>备注</th><th>时间</th></tr>
        @forelse ($balanceLogs as $log)
        <tr>
            <td>{{ $log->type === 'recharge' ? '充值' : '消费' }}</td>
            <td>{{ $log->amount > 0 ? '+' : '' }}{{ number_format($log->amount, 2) }}</td>
            <td>¥{{ number_format($log->balance_after, 2) }}</td>
            <td>{{ $log->remark }}</td>
            <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
        </tr>
        @empty
        <tr><td colspan="5" style="color:#acb5c9">暂无流水</td></tr>
        @endforelse
    </table>
</div>
@endsection
