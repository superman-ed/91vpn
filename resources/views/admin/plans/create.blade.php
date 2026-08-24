@extends('layouts.admin')
@section('title', '添加套餐组')
@section('content')
<div class="card"><div class="card-body">
<p class="text-muted">填写一次权益 + 各时长价格，会为每个填了价格的时长各建一个<strong>同名套餐</strong>；用户端会自动合并为一张卡、卡内切换时长。价格留空表示该时长不售卖。</p>
<form method="POST" action="/admin/plans">@csrf
<div class="row">
    <div class="form-group col-md-3"><label>名称（同档必须一致）</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
    <div class="form-group col-md-2"><label>流量 GB<br><small class="text-muted">月配额/总量</small></label><input name="transfer_gb" type="number" value="{{ old('transfer_gb') }}" class="form-control" required></div>
    <div class="form-group col-md-2"><label>流量重置</label><select name="reset_type" class="form-control"><option value="monthly" @selected(old('reset_type','monthly')==='monthly')>每30天重置</option><option value="none" @selected(old('reset_type')==='none')>总量不重置</option></select></div>
    <div class="form-group col-md-1"><label>等级</label><input name="class" type="number" value="{{ old('class', 1) }}" class="form-control" required></div>
    <div class="form-group col-md-2"><label>限速 Mbps（0不限）</label><input name="speed_limit" type="number" value="{{ old('speed_limit', 0) }}" class="form-control"></div>
    <div class="form-group col-md-2"><label>设备数（0不限）</label><input name="ip_limit" type="number" value="{{ old('ip_limit', 0) }}" class="form-control"></div>
</div>
<div class="row">
    <div class="form-group col-md-4"><label>套餐类型</label><select name="is_data_pack" class="form-control"><option value="0" @selected(old('is_data_pack','0')==='0')>普通套餐（多时长）</option><option value="1" @selected(old('is_data_pack')==='1')>流量包（加油包·单件立即生效）</option></select><small class="text-muted">选“流量包”时仅用下方「1个月」那格的价格建一件，立即给当前周期加流量。</small></div>
</div>
<hr>
<label class="font-weight-bold">各时长价格（¥）</label>
<div class="row">
    @foreach(['month' => '1个月 (30天)', 'quarter' => '3个月 (90天)', 'half_year' => '6个月 (180天)', 'year' => '12个月 (365天)'] as $key => $label)
    <div class="form-group col-md-3"><label>{{ $label }}</label><input name="prices[{{ $key }}]" type="number" step="0.01" value="{{ old('prices.'.$key) }}" class="form-control" placeholder="留空=不售卖"></div>
    @endforeach
</div>
@error('prices')<div class="text-danger mb-2">{{ $message }}</div>@enderror
<div class="row">
    <div class="form-group col-md-3"><label>排序</label><input name="sort" type="number" value="{{ old('sort', 0) }}" class="form-control"></div>
    <div class="form-group col-md-3"><label>在售</label><select name="on_sale" class="form-control"><option value="1" selected>是</option><option value="0">否</option></select></div>
</div>
<button class="btn btn-primary">创建套餐组</button> <a href="/admin/plans" class="btn btn-light">取消</a>
</form></div></div>
@endsection
