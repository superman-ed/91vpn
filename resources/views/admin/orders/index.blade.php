@extends('layouts.admin')
@section('title', '订单管理')
@section('content')
  @if(!empty($anomaly))
    {{-- `[!]` 说清楚当前是在看一个筛选出来的子集,而不是全部订单 ——
         否则人会以为"订单怎么只剩这几条了"。 --}}
    <div class="alert alert-warning" style="display:flex;gap:12px;align-items:center">
      <i class="fas fa-filter"></i>
      <div style="flex:1">正在查看「<strong>{{ $anomalyTitle }}</strong>」，不是全部订单。</div>
      <a href="/admin/orders" class="btn btn-sm btn-outline-secondary">看全部</a>
    </div>
  @endif

@php
    $tabs = ['' => '全部', 'paid' => '已支付', 'pending' => '待支付', 'queued' => '排队中', 'cancelled' => '已取消', 'refunded' => '已退款'];
    $payName = ['balance' => '余额', 'alipay' => '支付宝', 'wechat' => '微信', 'wxpay' => '微信', 'usdt' => 'USDT', 'epay' => '网关', 'mock' => '模拟', 'free' => '免费', 'admin' => '管理员开通', 'manual' => '手动标记'];
@endphp
<div class="adm-head">
    <h4><i class="fas fa-receipt text-primary"></i> 订单管理</h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="hidden" name="status" value="{{ $status }}">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索用户邮箱 / 订单号" style="min-width:180px">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($q || $from || $to)<a href="/admin/orders{{ $status ? '?status='.$status : '' }}" class="btn btn-light" style="border-radius:9px">清除</a>@endif
        <a href="/admin/orders/export?{{ http_build_query(request()->only('status','q','from','to')) }}" class="btn btn-light" style="border-radius:9px" title="按当前筛选导出"><i class="fas fa-file-csv text-success"></i> 导出</a>
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">¥{{ number_format($totalRevenue, 2) }}</div><div style="font-size:12.5px;opacity:.9">累计收入(实收)</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#ffb020,#ff9f1a)"><div style="font-size:22px;font-weight:800">−¥{{ number_format($totalRebate, 2) }}</div><div style="font-size:12.5px;opacity:.9">累计返佣支出</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#7c4ddb,#6636c0)"><div style="font-size:22px;font-weight:800">¥{{ number_format($netProfit, 2) }}</div><div style="font-size:12.5px;opacity:.9">纯毛利(收入−返佣)</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#5a67e8)"><div style="font-size:22px;font-weight:800">¥{{ number_format($todayRevenue, 2) }}</div><div style="font-size:12.5px;opacity:.9">今日收入</div></div>
</div>

<div class="adm-tools" style="margin-bottom:18px">
    @foreach($tabs as $k => $label)
    <a href="/admin/orders?status={{ $k }}{{ $q ? '&q='.$q : '' }}{{ $from ? '&from='.$from : '' }}{{ $to ? '&to='.$to : '' }}" class="btn btn-sm {{ (string) $status === $k ? 'adm-btn' : 'btn-light' }}" style="border-radius:9px">{{ $label }} <span style="opacity:.7">{{ $k ? ($counts[$k] ?? 0) : $counts['all'] }}</span></a>
    @endforeach
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>订单号</th><th>用户</th><th>套餐</th><th>金额</th><th>状态</th><th>支付方式</th><th>时间</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($orders as $o)
            <tr>
                <td>
                    <span style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;color:#34395e">{{ $o->order_no }}</span>
                    @if($o->trade_no)<br><span style="font-size:11px;color:#98a6ad" title="网关交易号">交易号 {{ $o->trade_no }}</span>@endif
                </td>
                <td style="color:#34395e;font-weight:600">{{ $o->user?->ident() ?? '—' }}</td>
                <td>{{ $o->plan?->name ?? '—' }}</td>
                <td>
                    <span style="font-weight:700;color:#34395e">¥{{ number_format($o->amount, 2) }}</span>
                    @if($o->coupon_id && $o->plan)
                        @php $discount = max(0, (float) $o->plan->price - (float) $o->amount); @endphp
                        @if($discount > 0)<br><span class="adm-pill ok" style="font-size:11px">券抵 ¥{{ number_format($discount, 2) }}</span>@endif
                    @endif
                </td>
                <td>
                    @switch($o->status)
                        @case('paid')<span class="adm-pill ok">已支付</span>@break
                        @case('queued')<span class="adm-pill info">排队中</span>@break
                        @case('pending')<span class="adm-pill warn">待支付</span>@break
                        @default<span class="adm-pill muted">已取消</span>
                    @endswitch
                </td>
                <td>{{ $o->pay_method ? ($payName[$o->pay_method] ?? $o->pay_method) : '—' }}</td>
                <td class="text-muted">{{ $o->created_at?->format('Y-m-d H:i') }}</td>
                <td>
                    @if($o->status === 'pending')
                        <form method="POST" action="/admin/orders/{{ $o->id }}/mark-paid" class="d-inline" data-dgr="确认将该订单标记为已支付并发货？">@csrf<button class="btn btn-success btn-sm">标记支付</button></form>
                        <form method="POST" action="/admin/orders/{{ $o->id }}/cancel" class="d-inline" data-dgr="确认取消该订单？">@csrf<button class="btn btn-outline-danger btn-sm">取消</button></form>
                    @elseif(in_array($o->status, \App\Services\RefundService::REFUNDABLE, true))
                        {{-- `[!!]` 退款【只退钱】。把做不到的事摆在按下之前 ——
                             人是按界面上写的去理解系统行为的，事前说明比事后解释便宜得多。 --}}
                        <button type="button" class="btn btn-outline-warning btn-sm js-refund"
                                data-id="{{ $o->id }}" data-no="{{ $o->order_no }}"
                                data-amount="{{ $o->amount }}"
                                data-delivered="{{ $o->delivered_at ? 1 : 0 }}"
                                data-caveats="{{ implode('||', \App\Services\RefundService::caveats($o)) }}">退款</button>
                    @elseif($o->status === 'refunded')
                        <span class="text-muted" title="{{ $o->refund_reason }}">已退 ¥{{ number_format((float) $o->refund_amount, 2) }}</span>
                    @else — @endif
                </td>
            </tr>
            @empty<tr><td colspan="8"><div class="adm-empty"><i class="fas fa-receipt fa-2x mb-2 d-block"></i>暂无订单</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pager', ['p' => $orders])
</div>
@endsection

@push('scripts')
<div class="modal fade" id="refundModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST" id="refundForm">@csrf
    <div class="modal-header"><h5 class="modal-title">退款 <span id="rfNo" class="text-muted"></span></h5></div>
    <div class="modal-body">
      {{-- 做不到的事放在最上面，不是折叠在下面 --}}
      <div class="alert alert-warning" id="rfCaveats" style="font-size:13px"></div>
      <div class="form-group">
        <label>退款金额</label>
        <input name="amount" id="rfAmount" class="form-control" required>
        <div class="hint">可以少于订单金额（部分退款）。原始金额不会被改动。</div>
      </div>
      <div class="form-group">
        <label>退款原因 <span class="text-danger">*</span></label>
        <input name="reason" class="form-control" required placeholder="例如：用户申请 / 重复下单 / 服务不可用">
        <div class="hint">不填原因的退款，以后对账时说不清是怎么回事。</div>
      </div>
      <label class="custom-switch" id="rfEndWrap">
        <input type="checkbox" name="end_package" value="1" class="custom-switch-input">
        <span class="custom-switch-indicator"></span>
        <span class="custom-switch-description">同时<strong>立即结束该用户当前套餐</strong></span>
      </label>
      <div class="hint" id="rfEndHint">不勾的话，用户的套餐继续有效 —— 退款不会自动收回权益。</div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-dismiss="modal">算了</button>
      <button class="btn btn-warning">确认退款</button>
    </div>
  </form>
</div></div></div>
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('.js-refund'); if (!b) return;
  document.getElementById('refundForm').action = '/admin/orders/' + b.dataset.id + '/refund';
  document.getElementById('rfNo').textContent = b.dataset.no;
  document.getElementById('rfAmount').value = b.dataset.amount;
  var cav = (b.dataset.caveats || '').split('||').filter(Boolean);
  document.getElementById('rfCaveats').innerHTML = cav.length
    ? '<strong>退款不会做这些：</strong><ul style="margin:6px 0 0;padding-left:18px">'
      + cav.map(function (c) { return '<li>' + c + '</li>'; }).join('') + '</ul>'
    : '这笔订单尚未发货，没有权益需要处理。';
  var delivered = b.dataset.delivered === '1';
  document.getElementById('rfEndWrap').style.display = delivered ? '' : 'none';
  document.getElementById('rfEndHint').style.display = delivered ? '' : 'none';
  $('#refundModal').modal('show');
});
</script>
@endpush
