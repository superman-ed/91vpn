@extends('layouts.user')
@section('title', '商店')
@section('head')
<style>
.shop-row > [class*="col-"] { display: flex; margin-bottom: 20px; }
.plan-card {
    width: 100%; margin-bottom: 0; display: flex; flex-direction: column;
    border: none; border-radius: 12px; overflow: hidden;
    box-shadow: 0 5px 18px rgba(103,119,239,.10);
    transition: transform .2s ease, box-shadow .2s ease;
}
.plan-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(103,119,239,.20); }
.plan-head {
    background: linear-gradient(135deg, #6777ef 0%, #5a67e8 100%);
    color: #fff; text-align: center; padding: 15px 12px 12px;
}
.plan-head h4 { color: #fff; margin: 0; font-weight: 700; font-size: 16px; letter-spacing: .3px; }
.plan-card .card-body { display: flex; flex-direction: column; flex: 1; padding: 15px; }
.dur-group { display: flex; gap: 3px; background: #f1f3fb; padding: 3px; border-radius: 8px; margin-bottom: 14px; }
.dur-group .dur-btn {
    flex: 1; min-width: 36px; border: none; border-radius: 6px; padding: 5px 0;
    background: transparent; color: #7a869a; font-weight: 600; font-size: 12px; cursor: pointer;
    transition: all .15s ease;
}
.dur-group .dur-btn.active { background: #34395e; color: #fff; box-shadow: 0 2px 6px rgba(52,57,94,.35); }
.plan-price { font-size: 30px; font-weight: 800; color: #34395e; line-height: 1; }
.plan-days { color: #98a6ad; font-size: 12px; margin-top: 3px; }
.plan-feature { padding: 5px 0; font-size: 12.5px; color: #54667a; display: flex; align-items: flex-start; }
.plan-feature i { width: 18px; color: #63c76a; margin-top: 3px; flex-shrink: 0; }
.buy-btn {
    border-radius: 9px; padding: 9px; font-weight: 700; font-size: 14px; color: #fff; border: none;
    background: linear-gradient(135deg, #6777ef 0%, #5a67e8 100%);
    box-shadow: 0 5px 14px rgba(103,119,239,.3);
}
.buy-btn:hover { color: #fff; filter: brightness(1.05); }
.coupon-input { border-radius: 8px; font-size: 13px; }
</style>
@endsection
@section('content')
@if($errors->has('plan_id'))
<div class="alert alert-danger">{{ $errors->first('plan_id') }}</div>
@endif
@if($groups->isEmpty() && $dataPacks->isEmpty())
    <div class="card"><div class="card-body text-center text-muted py-5">暂无在售套餐，敬请期待。</div></div>
@else
@if($groups->isNotEmpty())
<div class="row shop-row">
    @foreach($groups as $g)
    @php
        $b = $g['benefits'];
        $first = $g['durations']->first();
        $cnMonths = [1 => '一', 3 => '三', 6 => '六', 12 => '十二'];
        $trafficText = function ($d) use ($cnMonths) {
            if (! $d['monthly_reset']) {
                return $d['days'].'天总计 '.$d['total_gb'].'GB 流量（不重置）';
            }
            return $d['months'] <= 1
                ? '每月 '.$d['total_gb'].'GB 流量'
                : ($cnMonths[$d['months']] ?? $d['months']).'个月总计 '.$d['total_gb'].'GB 流量';
        };
    @endphp
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card plan-card">
            <div class="plan-head"><h4>{{ $b->name }}</h4></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <span class="plan-price">¥<span data-price-out>{{ $first['price'] }}</span></span>
                    <div class="plan-days">有效期 <span data-days-out>{{ $first['days'] }}</span> 天</div>
                </div>
                <div class="mb-3">
                    <div class="plan-feature"><i class="fas fa-check"></i><span>IEPL 专线隧道出口</span></div>
                    <div class="plan-feature"><i class="fas fa-check"></i><span data-traffic-out>{{ $trafficText($first) }}</span></div>
                    <div class="plan-feature"><i class="fas fa-check"></i><span>最大同时在线设备数 {{ $b->ip_limit > 0 ? $b->ip_limit.' 个' : '不限' }}</span></div>
                    <div class="plan-feature"><i class="fas fa-check"></i><span>{{ $b->speed_limit > 0 ? '端口限速 '.$b->speed_limit.'Mbps' : '端口不限速' }}</span></div>
                    <div class="plan-feature"><i class="fas fa-check"></i><span>稳定解锁 NetFlix 等流媒体</span></div>
                    <div class="plan-feature"><i class="fas fa-check"></i><span>有限售后支持（网站右下角客服或工单）</span></div>
                    <div class="plan-feature text-warning" data-stock-line style="{{ $first['stock'] > 0 ? '' : 'display:none' }}"><i class="fas fa-fire" style="color:#ffa426"></i><span>限量剩余 <span data-stock-num>{{ max(0, $first['stock']) }}</span> 份</span></div>
                </div>
                <form method="POST" action="/user/order/create" class="mt-auto">@csrf
                    <input type="hidden" name="plan_id" value="{{ $first['plan_id'] }}" data-plan-input>
                    <div class="dur-group">
                        @foreach($g['durations'] as $d)
                        <button type="button"
                            class="dur-btn {{ $loop->first ? 'active' : '' }}"
                            onclick="pickDuration(this)"
                            data-plan="{{ $d['plan_id'] }}" data-price="{{ $d['price'] }}"
                            data-days="{{ $d['days'] }}" data-stock="{{ $d['stock'] }}"
                            data-traffic="{{ $trafficText($d) }}"
                            data-soldout="{{ $d['sold_out'] ? 1 : 0 }}">{{ $d['label'] }}</button>
                        @endforeach
                    </div>
                    <button class="btn buy-btn btn-block" data-buy style="{{ $first['sold_out'] ? 'display:none' : '' }}"><i class="fas fa-shopping-cart"></i> 立即购买</button>
                    <button type="button" class="btn btn-light btn-block" data-soldout-btn disabled style="{{ $first['sold_out'] ? '' : 'display:none' }}">已售罄</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@if($dataPacks->isNotEmpty())
<h5 class="mb-2 mt-2"><i class="fas fa-bolt text-warning"></i> 流量包（立即生效）</h5>
<div class="alert alert-warning py-2 mb-3" style="font-size:13px"><i class="fas fa-circle-exclamation"></i> 重要提示：购买的流量包将会在您的<strong>会员到期日</strong>或<strong>流量重置日</strong>自动清零，请根据您的实际使用流量选择合适的流量包。</div>
<div class="row shop-row">
    @foreach($dataPacks as $p)
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card plan-card">
            <div class="plan-head" style="background:linear-gradient(135deg,#ffb020 0%,#ff9f1a 100%)"><h4>{{ $p['name'] }}</h4></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <span class="plan-price">+{{ $p['transfer_gb'] }}<small style="font-size:14px">GB</small></span>
                    <div class="plan-days">立即加到当前套餐</div>
                </div>
                <div class="mb-3">
                    <div class="plan-feature"><i class="fas fa-check"></i><span>支付后立即生效，不占用排队</span></div>
                    <div class="plan-feature"><i class="fas fa-check"></i><span>不改变套餐等级与到期时间</span></div>
                    <div class="plan-feature"><i class="fas fa-check"></i><span>本周期用完流量的加油首选</span></div>
                    <div class="plan-feature text-warning" style="{{ $p['stock'] > 0 ? '' : 'display:none' }}"><i class="fas fa-fire" style="color:#ffa426"></i><span>限量剩余 {{ max(0, $p['stock']) }} 份</span></div>
                </div>
                <div class="text-center mb-2"><span class="plan-price" style="font-size:22px">¥{{ $p['price'] }}</span></div>
                <form method="POST" action="/user/order/create" class="mt-auto">@csrf
                    <input type="hidden" name="plan_id" value="{{ $p['plan_id'] }}">
                    @if($p['sold_out'])
                    <button type="button" class="btn btn-light btn-block" disabled>已售罄</button>
                    @else
                    <button class="btn buy-btn btn-block"><i class="fas fa-shopping-cart"></i> 购买流量包</button>
                    @endif
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endif
<script>
function pickDuration(btn){
    var card = btn.closest('.plan-card');
    card.querySelectorAll('.dur-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    card.querySelector('[data-price-out]').textContent = btn.dataset.price;
    card.querySelector('[data-days-out]').textContent = btn.dataset.days;
    card.querySelector('[data-traffic-out]').textContent = btn.dataset.traffic;
    card.querySelector('[data-plan-input]').value = btn.dataset.plan;
    var stock = parseInt(btn.dataset.stock, 10);
    var stockLine = card.querySelector('[data-stock-line]');
    if (stock > 0) { stockLine.style.display = ''; card.querySelector('[data-stock-num]').textContent = stock; }
    else { stockLine.style.display = 'none'; }
    var soldOut = btn.dataset.soldout === '1';
    card.querySelector('[data-buy]').style.display = soldOut ? 'none' : '';
    card.querySelector('[data-soldout-btn]').style.display = soldOut ? '' : 'none';
}
</script>
@endsection
