@extends('layouts.admin')
@section('title', $plan->exists ? '编辑套餐' : '添加套餐')
@section('content')
<div class="card"><div class="card-body">
<form method="POST" action="{{ $plan->exists ? '/admin/plans/'.$plan->id : '/admin/plans' }}">@csrf @if($plan->exists)@method('PUT')@endif
<div class="row">
<div class="form-group col-md-6"><label>名称</label><input name="name" value="{{ old('name',$plan->name) }}" class="form-control" required></div>
<div class="form-group col-md-6"><label>价格 ¥</label><input name="price" type="number" step="0.01" value="{{ old('price',$plan->price) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>周期</label><select name="period" class="form-control"><option value="month">月</option><option value="quarter">季</option><option value="half_year">半年</option><option value="year">年</option></select></div>
<div class="form-group col-md-4"><label>流量 GB</label><input name="transfer_gb" type="number" value="{{ old('transfer_gb',$plan->transfer_gb) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>等级</label><input name="class" type="number" value="{{ old('class',$plan->class ?? 1) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>时长（天）</label><input name="duration_days" type="number" value="{{ old('duration_days',$plan->duration_days) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>限速 Mbps（0不限）</label><input name="speed_limit" type="number" value="{{ old('speed_limit',$plan->speed_limit ?? 0) }}" class="form-control"></div>
<div class="form-group col-md-4"><label>设备数</label><input name="ip_limit" type="number" value="{{ old('ip_limit',$plan->ip_limit ?? 0) }}" class="form-control"></div>
<div class="form-group col-md-4"><label>排序</label><input name="sort" type="number" value="{{ old('sort',$plan->sort ?? 0) }}" class="form-control"></div>
<div class="form-group col-md-4"><label>在售</label><select name="on_sale" class="form-control"><option value="1" @selected(old('on_sale',$plan->on_sale ?? 1))>是</option><option value="0">否</option></select></div>
</div>
<button class="btn btn-primary">保存</button> <a href="/admin/plans" class="btn btn-light">取消</a>
</form></div></div>
@endsection
