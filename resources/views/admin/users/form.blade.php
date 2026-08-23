@extends('layouts.admin')
@section('title', '编辑用户')
@section('content')
<div class="card"><div class="card-header"><h4>{{ $user->email }}（ID {{ $user->id }}）</h4></div><div class="card-body">
<form method="POST" action="/admin/users/{{ $user->id }}">@csrf @method('PUT')
<div class="row">
<div class="form-group col-md-4"><label>等级</label><input name="class" type="number" value="{{ old('class',$user->class) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>流量配额（GB）</label><input name="transfer_enable_gb" type="number" step="0.1" value="{{ old('transfer_enable_gb',bytes_to_gb($user->transfer_enable)) }}" class="form-control" required></div>
<div class="form-group col-md-4"><label>到期时间</label><input name="class_expire" type="date" value="{{ old('class_expire',$user->class_expire?->format('Y-m-d')) }}" class="form-control"></div>
<div class="form-group col-md-4"><label>限速 Mbps</label><input name="node_speed_limit" type="number" value="{{ old('node_speed_limit',$user->node_speed_limit) }}" class="form-control"></div>
<div class="form-group col-md-4"><label>设备数</label><input name="node_ip_limit" type="number" value="{{ old('node_ip_limit',$user->node_ip_limit) }}" class="form-control"></div>
<div class="form-group col-md-4"><label>余额 ¥</label><input name="money" type="number" step="0.01" value="{{ old('money',$user->money) }}" class="form-control"></div>
</div>
<button class="btn btn-primary">保存</button> <a href="/admin/users" class="btn btn-light">取消</a>
</form></div></div>
@endsection
