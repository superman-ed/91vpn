@extends('layouts.guest')
@section('title', '登录')
@section('content')
<form method="POST" action="/login">
    @csrf
    <label>邮箱</label>
    <input type="email" name="email" value="{{ old('email') }}" required>

    <label>密码</label>
    <input type="password" name="password" required>

    <label>验证码：{{ $captchaQuestion }}</label>
    <input type="text" name="captcha" required>

    <label style="display:flex;align-items:center;gap:6px;margin-top:14px">
        <input type="checkbox" name="remember" style="width:auto"> 记住我
    </label>

    <button type="submit">登录</button>
</form>
<div class="muted">还没账号？<a href="/register">点击注册</a></div>
@endsection
