@extends('layouts.admin')
@section('title', '添加套餐组')
@section('content')
<div class="card"><div class="card-body">
<p class="text-muted">填写一次权益 + 各时长价格，会为每个填了价格的时长各建一个<strong>同名套餐</strong>；用户端会自动合并为一张卡、卡内切换时长。价格留空表示该时长不售卖。</p>
<form method="POST" action="/admin/plans">@csrf
<div class="row">
    <div class="form-group col-md-4"><label>名称（同档必须一致）</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
    <div class="form-group col-md-2"><label>月流量 GB</label><input name="transfer_gb" type="number" value="{{ old('transfer_gb') }}" class="form-control" required></div>
    <div class="form-group col-md-2"><label>等级</label><input name="class" type="number" value="{{ old('class', 1) }}" class="form-control" required></div>
    <div class="form-group col-md-2"><label>限速 Mbps（0不限）</label><input name="speed_limit" type="number" value="{{ old('speed_limit', 0) }}" class="form-control"></div>
    <div class="form-group col-md-2"><label>设备数（0不限）</label><input name="ip_limit" type="number" value="{{ old('ip_limit', 0) }}" class="form-control"></div>
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
