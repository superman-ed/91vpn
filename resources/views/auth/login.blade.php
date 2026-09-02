@extends('layouts.guest')
@section('title', '登录')
@section('content')
<form method="POST" action="/login">@csrf
    <div class="form-group">
        <label>账户名</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
            <input type="text" name="username" value="{{ old('username') }}" class="form-control" placeholder="账户名" required autofocus autocomplete="username">
        </div>
    </div>
    <div class="form-group">
        <label>密码</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
            <input type="password" name="password" class="form-control" placeholder="登录密码" required>
        </div>
    </div>
    <div class="form-group">
        <label>验证码：{{ $captchaQuestion }}</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-calculator"></i></span></div>
            <input type="text" name="captcha" class="form-control" placeholder="请计算结果" required>
        </div>
    </div>
    <div class="form-group">
        <div class="custom-control custom-checkbox"><input type="checkbox" name="remember" class="custom-control-input" id="remember"><label class="custom-control-label" for="remember" style="font-weight:500;color:#7a869a">记住我</label></div>
    </div>
    <button class="btn btn-auth btn-block mb-3"><i class="fas fa-sign-in-alt"></i> 登 录</button>
    <div class="auth-links"><span style="color:#9a9aa0">忘记密码请联系在线客服</span> · <a href="/register">注册新账号</a></div>
</form>
@endsection
