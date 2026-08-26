@extends('layouts.guest')
@section('title', '注册')
@section('content')
<style>.auth-inner .form-group { margin-bottom: .75rem; } .auth-inner label { margin-bottom: 3px; }</style>
<form method="POST" action="/register">@csrf
    <div class="form-group">
        <label>注册邮箱</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="you@example.com" required>
        </div>
    </div>
    <div class="form-group">
        <label>邮箱验证码</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-shield-alt"></i></span></div>
            <input type="text" name="email_code" class="form-control" placeholder="邮箱收到的验证码" required>
            <div class="input-group-append"><button type="button" class="btn btn-outline-primary" data-send-code data-endpoint="/auth/send" style="border-radius:0 10px 10px 0">发送</button></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group col-6">
            <label>昵称</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control" placeholder="昵称" required>
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
