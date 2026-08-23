@extends('layouts.user')
@section('title', '商店')
@section('head')
<style>
.shop-row > [class*="col-"] { display: flex; margin-bottom: 25px; }
.plan-card { width: 100%; margin-bottom: 0; display: flex; flex-direction: column; }
.plan-card .card-body { display: flex; flex-direction: column; flex: 1; }
.plan-price { font-size: 36px; font-weight: 800; color: #6777ef; line-height: 1; }
.plan-feature { padding: 6px 0; border-bottom: 1px solid #f2f4f6; font-size: 14px; }
.plan-feature i { width: 18px; }
.dur-group { display: flex; gap: 6px; flex-wrap: wrap; }
.dur-group .dur-btn { flex: 1; min-width: 56px; }
</style>
@endsection
@section('content')
@if($groups->isEmpty())
    <div class="card"><div class="card-body text-center text-muted py-5">暂无在售套餐，敬请期待。</div></div>
@else
<div class="row shop-row">
    @foreach($groups as $g)
    @php $b = $g['benefits']; $first = $g['durations']->first(); @endphp
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card plan-card">
            <div class="card-header"><h4 style="color:#6777ef">{{ $b->name }}</h4></div>
            <div class="card-body">
                <div class="dur-group mb-3">
                    @foreach($g['durations'] as $d)
                    <button type="button"
                        class="btn btn-sm dur-btn {{ $loop->first ? 'btn-primary' : 'btn-outline-primary' }}"
                        onclick="pickDuration(this)"
                        data-plan="{{ $d['plan_id'] }}" data-price="{{ $d['price'] }}"
                        data-days="{{ $d['days'] }}" data-stock="{{ $d['stock'] }}"
                        data-soldout="{{ $d['sold_out'] ? 1 : 0 }}">{{ $d['label'] }}</button>
                    @endforeach
                </div>
                <div class="text-center mb-3">
                    <span class="plan-price">¥<span data-price>{{ $first['price'] }}</span></span>
                    <div class="text-muted mt-1" style="font-size:13px">有效期 <span data-days>{{ $first['days'] }}</span> 天</div>
                </div>
                <div class="mb-3">
                    <div class="plan-feature"><i class="fas fa-database text-success"></i> {{ $b->transfer_gb }} GB / 月</div>
                    <div class="plan-feature"><i class="fas fa-mobile-alt text-success"></i> {{ $b->ip_limit > 0 ? $b->ip_limit.' 台设备' : '设备不限' }}</div>
                    <div class="plan-feature"><i class="fas fa-tachometer-alt text-success"></i> {{ $b->speed_limit > 0 ? $b->speed_limit.' Mbps' : '不限速' }}</div>
                    <div class="plan-feature"><i class="fas fa-crown text-success"></i> 等级 {{ class_name($b->class) }}</div>
                    <div class="plan-feature text-warning" data-stock-line style="{{ $first['stock'] > 0 ? '' : 'display:none' }}"><i class="fas fa-fire"></i> 限量剩余 <span data-stock-num>{{ max(0, $first['stock']) }}</span> 份</div>
                </div>
                <form method="POST" action="/user/order/create" class="mt-auto">@csrf
                    <input type="hidden" name="plan_id" value="{{ $first['plan_id'] }}" data-plan-input>
                    <input type="text" name="coupon" class="form-control mb-2" placeholder="优惠码（选填）" data-coupon style="{{ $first['sold_out'] ? 'display:none' : '' }}">
                    <button class="btn btn-primary btn-block" data-buy style="{{ $first['sold_out'] ? 'display:none' : '' }}"><i class="fas fa-shopping-cart"></i> 购买</button>
                    <button type="button" class="btn btn-light btn-block" data-soldout-btn disabled style="{{ $first['sold_out'] ? '' : 'display:none' }}">已售罄</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
<script>
function pickDuration(btn){
    var card = btn.closest('.plan-card');
    card.querySelectorAll('.dur-btn').forEach(function(b){ b.classList.remove('btn-primary'); b.classList.add('btn-outline-primary'); });
    btn.classList.remove('btn-outline-primary'); btn.classList.add('btn-primary');
    card.querySelector('[data-price]').textContent = btn.dataset.price;
    card.querySelector('[data-days]').textContent = btn.dataset.days;
    card.querySelector('[data-plan-input]').value = btn.dataset.plan;
    var stock = parseInt(btn.dataset.stock, 10);
    var stockLine = card.querySelector('[data-stock-line]');
    if (stock > 0) { stockLine.style.display = ''; card.querySelector('[data-stock-num]').textContent = stock; }
    else { stockLine.style.display = 'none'; }
    var soldOut = btn.dataset.soldout === '1';
    card.querySelector('[data-coupon]').style.display = soldOut ? 'none' : '';
    card.querySelector('[data-buy]').style.display = soldOut ? 'none' : '';
    card.querySelector('[data-soldout-btn]').style.display = soldOut ? '' : 'none';
}
</script>
@endif
@endsection
