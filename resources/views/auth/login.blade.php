@extends('layouts.guest')
@section('title', '登录')
@section('content')
<form method="POST" action="/login">@csrf
    <div class="form-group"><label>邮箱</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus></div>
    <div class="form-group"><label>密码</label><input type="password" name="password" class="form-control" required></div>
    <div class="form-group"><label>验证码：{{ $captchaQuestion }}</label><input type="text" name="captcha" class="form-control" required></div>
    <div class="form-group"><div class="custom-control custom-checkbox"><input type="checkbox" name="remember" class="custom-control-input" id="remember"><label class="custom-control-label" for="remember">记住我</label></div></div>
    <div class="form-group"><button class="btn btn-primary btn-lg btn-block">登录</button></div>
    <div class="text-center"><a href="/password/forgot">忘记密码？</a> · <a href="/register">注册新账号</a></div>
</form>
@endsection
