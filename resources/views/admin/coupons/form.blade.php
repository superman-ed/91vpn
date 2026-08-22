@extends('layouts.admin')
@section('title', '生成优惠券')
@section('content')
<div class="panel"><form method="POST" action="/admin/coupons">@csrf
<div class="grid2">
<div><label>优惠码</label><input name="code" value="{{ old('code') }}" style="width:100%" required></div>
<div><label>类型</label><select name="type" style="width:100%"><option value="percent">百分比折扣</option><option value="amount">固定金额减</option></select></div>
<div><label>额度（百分比填0-100，固定减填元）</label><input name="value" type="number" step="0.01" style="width:100%" required></div>
<div><label>使用次数上限（留空=无限）</label><input name="max_use" type="number" style="width:100%"></div>
<div><label>到期时间（留空=永久）</label><input name="expires_at" type="date" style="width:100%"></div>
</div>
<div style="margin-top:20px"><button class="btn">生成</button> <a href="/admin/coupons" class="btn ghost">取消</a></div>
</form></div>
@endsection
