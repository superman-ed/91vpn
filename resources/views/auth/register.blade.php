@extends('layouts.guest')
@section('title', '注册')
@section('content')
<form method="POST" action="/register">@csrf
    <div class="form-group"><label>注册邮箱</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
    <div class="form-group"><label>邮箱验证码</label>
        <div class="input-group"><input type="text" name="email_code" class="form-control" required>
            <div class="input-group-append"><button type="button" class="btn btn-outline-primary" id="sendCode">发送</button></div></div></div>
    <div class="form-group"><label>昵称</label><input type="text" name="name" value="{{ old('name') }}" class="form-control" required></div>
    <div class="form-group"><label>邀请码（选填）</label><input type="text" name="invite_code" value="{{ old('invite_code', request('invite')) }}" class="form-control"></div>
    <div class="form-group"><label>密码</label><input type="password" name="password" class="form-control" required></div>
    <div class="form-group"><label>确认密码</label><input type="password" name="password_confirmation" class="form-control" required></div>
    <div class="form-group"><label>验证码：{{ $captchaQuestion }}</label><input type="text" name="captcha" class="form-control" required></div>
    <div class="form-group"><button class="btn btn-primary btn-lg btn-block">注册</button></div>
    <div class="text-center">已有账号？<a href="/login">点击登录</a></div>
</form>
<script>
document.getElementById('sendCode').addEventListener('click', async function(){
    const email=document.querySelector('input[name=email]').value;
    if(!email){alert('请先填写邮箱');return;}
    this.disabled=true;this.textContent='发送中...';
    try{const r=await fetch('/auth/send',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({email})});const j=await r.json();alert(j.message||'已发送');}catch(e){alert('发送失败');}
    this.disabled=false;this.textContent='发送';
});
</script>
@endsection
