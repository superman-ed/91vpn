@extends('layouts.admin')
@section('title', '编辑用户')
@section('content')
@php
    $used = $user->u + $user->d;
    $usedGb = bytes_to_gb($used);
    $totalGb = bytes_to_gb($user->transfer_enable);
    $pct = $user->transfer_enable > 0 ? min(100, round($used / $user->transfer_enable * 100)) : 0;
@endphp
<div class="adm-head">
    <h4><i class="fas fa-user-edit text-primary"></i> {{ $user->email }} <span class="text-muted" style="font-size:13px;font-weight:400">#{{ $user->id }}</span></h4>
    <a href="/admin/users" class="btn btn-light" style="border-radius:9px">返回</a>
</div>

<div class="card adm-form-card">
    <div class="card-header"><span class="ic"><i class="fas fa-info-circle"></i></span><h4>账号信息</h4></div>
    <div class="card-body">
        <div class="row" style="font-size:13.5px;color:#54667a">
            <div class="col-md-3 mb-2">状态：@if($user->banned)<span class="adm-pill danger">封禁</span>@elseif($user->class > 0 && $user->class_expire && $user->class_expire->isFuture())<span class="adm-pill ok">会员</span>@elseif($user->class > 0)<span class="adm-pill warn">已过期</span>@else<span class="adm-pill muted">免费</span>@endif</div>
            <div class="col-md-3 mb-2">注册时间：<span style="color:#34395e">{{ $user->created_at?->format('Y-m-d') }}</span></div>
            <div class="col-md-3 mb-2">最后使用：<span style="color:#34395e">{{ $user->last_used_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
            <div class="col-md-3 mb-2">邀请人：<span style="color:#34395e">{{ $user->inviter?->email ?? '—' }}</span></div>
            <div class="col-md-12">
                <div class="d-flex justify-content-between" style="font-size:12px"><span>已用流量 {{ number_format($usedGb, 1) }} / {{ number_format($totalGb, 1) }} GB</span><span>{{ $pct }}%</span></div>
                <div class="progress" style="height:7px;border-radius:5px"><div class="progress-bar {{ $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-primary') }}" style="width:{{ $pct }}%"></div></div>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="/admin/users/{{ $user->id }}" class="adm-form">@csrf @method('PUT')
    <div class="card adm-form-card">
        <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#63c76a,#3fae57)"><i class="fas fa-sliders-h"></i></span><h4>套餐 / 权益</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-4"><label>昵称</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control"></div>
                <div class="form-group col-md-4"><label>等级</label><input name="class" type="number" value="{{ old('class', $user->class) }}" class="form-control" required></div>
                <div class="form-group col-md-4"><label>到期时间</label><input name="class_expire" type="date" value="{{ old('class_expire', $user->class_expire?->format('Y-m-d')) }}" class="form-control"></div>
                <div class="form-group col-md-4"><label>流量配额（GB）</label><input name="transfer_enable_gb" type="number" step="0.1" value="{{ old('transfer_enable_gb', bytes_to_gb($user->transfer_enable)) }}" class="form-control" required></div>
                <div class="form-group col-md-4"><label>限速 Mbps（0不限）</label><input name="node_speed_limit" type="number" value="{{ old('node_speed_limit', $user->node_speed_limit) }}" class="form-control"></div>
                <div class="form-group col-md-4"><label>设备数（0不限）</label><input name="node_ip_limit" type="number" value="{{ old('node_ip_limit', $user->node_ip_limit) }}" class="form-control"></div>
                <div class="form-group col-md-4"><label>余额 ¥</label><input name="money" type="number" step="0.01" value="{{ old('money', $user->money) }}" class="form-control"></div>
            </div>
            <button class="btn adm-btn"><i class="fas fa-save"></i> 保存</button>
        </div>
    </div>
</form>

<div class="card adm-form-card">
    <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#fc544b,#e0362d)"><i class="fas fa-exclamation-triangle"></i></span><h4>账号操作</h4></div>
    <div class="card-body adm-form">
        <div class="d-flex flex-wrap align-items-end" style="gap:24px">
            <form method="POST" action="/admin/users/{{ $user->id }}/reset-traffic" onsubmit="return confirm('确认将该用户已用流量清零？')">@csrf
                <button class="btn btn-outline-primary" style="border-radius:9px"><i class="fas fa-sync-alt"></i> 重置已用流量</button>
            </form>
            @unless($user->is_admin)
            <form method="POST" action="/admin/users/{{ $user->id }}/toggle-ban" onsubmit="return confirm('{{ $user->banned ? '确认解封？' : '确认封禁该用户？' }}')">@csrf
                <button class="btn btn-outline-{{ $user->banned ? 'success' : 'danger' }}" style="border-radius:9px"><i class="fas fa-ban"></i> {{ $user->banned ? '解封账号' : '封禁账号' }}</button>
            </form>
            @endunless
            <form method="POST" action="/admin/users/{{ $user->id }}/reset-password" class="d-flex align-items-end" style="gap:8px" onsubmit="return confirm('确认重置该用户登录密码？')">@csrf
                <div class="form-group mb-0"><label>新登录密码</label><input name="password" type="text" class="form-control @error('password') is-invalid @enderror" placeholder="至少 8 位" style="min-width:180px" required></div>
                <button class="btn btn-outline-danger" style="border-radius:9px"><i class="fas fa-key"></i> 重置密码</button>
            </form>
        </div>
        @error('password')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</div>
@endsection
