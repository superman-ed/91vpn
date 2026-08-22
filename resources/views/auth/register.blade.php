@extends('layouts.guest')
@section('title', '注册')
@section('content')
<form method="POST" action="/register">
    @csrf
    <label>注册邮箱</label>
    <input type="email" name="email" value="{{ old('email') }}" required>

    <label>邮箱验证码</label>
    <div class="row">
        <input type="text" name="email_code" required>
        <button type="button" class="btn-sm" id="sendCode">发送</button>
    </div>

    <label>昵称</label>
    <input type="text" name="name" value="{{ old('name') }}" required>

    <label>邀请码（选填）</label>
    <input type="text" name="invite_code" value="{{ old('invite_code') }}">

    <label>密码</label>
    <input type="password" name="password" required>

    <label>确认密码</label>
    <input type="password" name="password_confirmation" required>

    <label>验证码：{{ $captchaQuestion }}</label>
    <input type="text" name="captcha" required>

    <button type="submit">注册</button>
</form>
<div class="muted">已有账号？<a href="/login">点击登录</a></div>
<script>
document.getElementById('sendCode').addEventListener('click', async function(){
    const email = document.querySelector('input[name=email]').value;
    if(!email){ alert('请先填写邮箱'); return; }
    this.disabled = true; this.textContent = '发送中...';
    try {
        const r = await fetch('/auth/send', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({email})});
        const j = await r.json();
        alert(j.message || '已发送，请查看（开发环境见服务器日志）');
    } catch(e){ alert('发送失败'); }
    this.disabled = false; this.textContent = '发送';
});
</script>
@endsection
