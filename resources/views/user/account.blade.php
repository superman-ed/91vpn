@extends('layouts.user')
@section('title', '账号设置')
@section('content')
<div class="row">
    <div class="col-12 col-lg-6">
        <div class="card"><div class="card-header"><h4>基本信息</h4></div><div class="card-body">
            <form method="POST" action="/user/account/profile">@csrf
                <div class="form-group"><label>邮箱</label><input class="form-control" value="{{ $user->email }}" disabled></div>
                <div class="form-group"><label>昵称</label><input name="name" class="form-control" value="{{ old('name',$user->name) }}" required></div>
                <button class="btn btn-primary">保存昵称</button>
            </form>
        </div></div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card"><div class="card-header"><h4>修改登录密码</h4></div><div class="card-body">
            <form method="POST" action="/user/account/password">@csrf
                <div class="form-group"><label>当前密码</label><input type="password" name="current_password" class="form-control" required></div>
                <div class="form-group"><label>新密码</label><input type="password" name="password" class="form-control" required></div>
                <div class="form-group"><label>确认新密码</label><input type="password" name="password_confirmation" class="form-control" required></div>
                <button class="btn btn-primary">修改密码</button>
            </form>
        </div></div>
    </div>
</div>
@endsection
