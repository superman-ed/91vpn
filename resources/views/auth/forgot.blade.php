@extends('layouts.guest')
@section('title', '找回密码')
@section('content')
<form method="POST" action="/password/reset">
    @csrf
    <label>注册邮箱</label>
    <div class="row">
        <input type="email" name="email" value="{{ old('email') }}" required>
        <button type="button" class="btn-sm" id="sendReset">发送验证码</button>
    </div>

    <label>验证码</label>
    <input type="text" name="code" required>

    <label>新密码</label>
    <input type="password" name="password" required>

    <label>确认新密码</label>
    <input type="password" name="password_confirmation" required>

    <button type="submit">重置密码</button>
</form>
<div class="muted"><a href="/login">返回登录</a></div>
<script>
document.getElementById('sendReset').addEventListener('click', async function(){
    const email = document.querySelector('input[name=email]').value;
    if(!email){ alert('请先填写邮箱'); return; }
    this.disabled=true; this.textContent='发送中...';
    try {
        const r = await fetch('/password/send',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({email})});
        const j = await r.json(); alert(j.message||'已发送');
    } catch(e){ alert('发送失败'); }
    this.disabled=false; this.textContent='发送验证码';
});
</script>
@endsection
