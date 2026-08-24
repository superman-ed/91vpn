@extends('layouts.admin')
@section('title', $coupon->exists ? '编辑优惠券' : '生成优惠券')
@section('content')
@php
    $type = old('type', $coupon->type ?? 'percent');
    $enabled = old('enabled', $coupon->exists ? (int) $coupon->enabled : 1);
    $show = old('show_on_checkout', (int) ($coupon->show_on_checkout ?? 0));
    $selPeriods = old('periods', $coupon->periods ?? []);
    $allPeriods = ['month' => '1月', 'quarter' => '3月', 'half_year' => '半年', 'year' => '年付'];
@endphp
<div class="card"><div class="card-body">
<form method="POST" action="{{ $coupon->exists ? '/admin/coupons/'.$coupon->id : '/admin/coupons' }}">@csrf @if($coupon->exists)@method('PUT')@endif
<div class="row">
<div class="form-group col-md-6"><label>优惠码</label><input name="code" value="{{ old('code', $coupon->code) }}" class="form-control" required></div>
<div class="form-group col-md-6"><label>类型</label><select name="type" class="form-control"><option value="percent" @selected($type==='percent')>百分比折扣</option><option value="amount" @selected($type==='amount')>固定金额减</option></select></div>
<div class="form-group col-md-4"><label>额度（百分比0-100 / 固定减元）</label><input name="value" type="number" step="0.01" value="{{ old('value', $coupon->value) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>使用次数上限（空=无限）</label><input name="max_use" type="number" value="{{ old('max_use', $coupon->exists && $coupon->max_use >= 0 ? $coupon->max_use : null) }}" class="form-control"></div>
<div class="form-group col-md-4"><label>到期时间（空=永久）</label><input name="expires_at" type="date" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}" class="form-control"></div>
<div class="form-group col-md-12"><label>适用时长（不勾=全部时长通用）</label><div>
@foreach($allPeriods as $k => $v)
<label class="mr-3"><input type="checkbox" name="periods[]" value="{{ $k }}" @checked(in_array($k, (array) $selPeriods, true))> {{ $v }}</label>
@endforeach
</div></div>
<div class="form-group col-md-8"><label>收银台备注文案（选填）</label><input name="note" value="{{ old('note', $coupon->note) }}" class="form-control" placeholder="如：VIP ①②③ 半年套餐 95 折优惠码"><small class="text-muted">勾选下方展示后，收银台会显示「此文案：优惠码」</small></div>
<div class="form-group col-md-2"><label>状态</label><select name="enabled" class="form-control"><option value="1" @selected($enabled==1)>启用</option><option value="0" @selected($enabled==0)>停用</option></select></div>
<div class="form-group col-md-2"><label>收银台展示</label><select name="show_on_checkout" class="form-control"><option value="0" @selected($show==0)>不展示</option><option value="1" @selected($show==1)>展示</option></select></div>
</div>
<button class="btn btn-primary">保存</button> <a href="/admin/coupons" class="btn btn-light">取消</a>
</form></div></div>
@endsection
