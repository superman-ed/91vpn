@extends('layouts.admin')
@section('title', '生成优惠券')
@section('content')
<div class="card"><div class="card-body">
<form method="POST" action="/admin/coupons">@csrf
<div class="row">
<div class="form-group col-md-6"><label>优惠码</label><input name="code" value="{{ old('code') }}" class="form-control" required></div>
<div class="form-group col-md-6"><label>类型</label><select name="type" class="form-control"><option value="percent">百分比折扣</option><option value="amount">固定金额减</option></select></div>
<div class="form-group col-md-4"><label>额度（百分比0-100 / 固定减元）</label><input name="value" type="number" step="0.01" class="form-control" required></div>
<div class="form-group col-md-4"><label>使用次数上限（空=无限）</label><input name="max_use" type="number" class="form-control"></div>
<div class="form-group col-md-4"><label>到期时间（空=永久）</label><input name="expires_at" type="date" class="form-control"></div>
</div>
<button class="btn btn-primary">生成</button> <a href="/admin/coupons" class="btn btn-light">取消</a>
</form></div></div>
@endsection
