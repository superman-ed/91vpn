@extends('layouts.guest')
@section('title', '注册')
@section('content')
<style>.auth-inner .form-group { margin-bottom: .75rem; } .auth-inner label { margin-bottom: 3px; }</style>
<form method="POST" action="/register">@csrf
    <div class="form-group">
        <label>账户名</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
            <input type="text" name="username" value="{{ old('username') }}" class="form-control" placeholder="4-20 位字母/数字/下划线" required autocomplete="username">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group col-6">
            <label>昵称（选填）</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-id-badge"></i></span></div>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control" placeholder="选填">
            </div>
        </div>
        <div class="form-group col-6">
            <label>邀请码（选填）</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-gift"></i></span></div>
                <input type="text" name="invite_code" value="{{ old('invite_code', request('invite')) }}" class="form-control" placeholder="选填">
            </div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group col-6">
            <label>密码</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                <input type="password" name="password" class="form-control" placeholder="至少 8 位" required>
            </div>
        </div>
        <div class="form-group col-6">
            <label>确认密码</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                <input type="password" name="password_confirmation" class="form-control" placeholder="再次输入" required>
            </div>
        </div>
    </div>
    <div class="form-group">
        <label>验证码：{{ $captchaQuestion }}</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-calculator"></i></span></div>
            <input type="text" name="captcha" class="form-control" placeholder="请计算结果" required>
        </div>
    </div>
    <button class="btn btn-auth btn-block mb-2 mt-1"><i class="fas fa-user-plus"></i> 注 册</button>
    <div class="auth-links">已有账号？<a href="/login">点击登录</a></div>
</form>
@endsection
