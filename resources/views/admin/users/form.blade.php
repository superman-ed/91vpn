@extends('layouts.admin')
@section('title', '编辑用户')
@section('content')
<div class="panel">
<p style="color:#6c757d">{{ $user->email }}（ID {{ $user->id }}）</p>
<form method="POST" action="/admin/users/{{ $user->id }}">@csrf @method('PUT')
<div class="grid2">
<div><label>等级</label><input name="class" type="number" value="{{ old('class',$user->class) }}" style="width:100%" required></div>
<div><label>流量配额（GB）</label><input name="transfer_enable_gb" type="number" step="0.1" value="{{ old('transfer_enable_gb', bytes_to_gb($user->transfer_enable)) }}" style="width:100%" required></div>
<div><label>到期时间</label><input name="class_expire" type="date" value="{{ old('class_expire', $user->class_expire?->format('Y-m-d')) }}" style="width:100%"></div>
<div><label>限速 Mbps</label><input name="node_speed_limit" type="number" value="{{ old('node_speed_limit',$user->node_speed_limit) }}" style="width:100%"></div>
<div><label>设备数</label><input name="node_ip_limit" type="number" value="{{ old('node_ip_limit',$user->node_ip_limit) }}" style="width:100%"></div>
<div><label>余额 ¥</label><input name="money" type="number" step="0.01" value="{{ old('money',$user->money) }}" style="width:100%"></div>
</div>
<div style="margin-top:20px"><button class="btn">保存</button> <a href="/admin/users" class="btn ghost">取消</a></div>
</form></div>
@endsection
