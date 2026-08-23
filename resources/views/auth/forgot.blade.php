@extends('layouts.guest')
@section('title', '找回密码')
@section('content')
<form method="POST" action="/password/reset">@csrf
    <div class="form-group"><label>注册邮箱</label>
        <div class="input-group"><input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
            <div class="input-group-append"><button type="button" class="btn btn-outline-primary" id="sendReset">发送验证码</button></div></div></div>
    <div class="form-group"><label>验证码</label><input type="text" name="code" class="form-control" required></div>
    <div class="form-group"><label>新密码</label><input type="password" name="password" class="form-control" required></div>
    <div class="form-group"><label>确认新密码</label><input type="password" name="password_confirmation" class="form-control" required></div>
    <div class="form-group"><button class="btn btn-primary btn-lg btn-block">重置密码</button></div>
    <div class="text-center"><a href="/login">返回登录</a></div>
</form>
<script>
document.getElementById('sendReset').addEventListener('click', async function(){
    const email=document.querySelector('input[name=email]').value;
    if(!email){alert('请先填写邮箱');return;}
    this.disabled=true;this.textContent='发送中...';
    try{const r=await fetch('/password/send',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({email})});const j=await r.json();alert(j.message||'已发送');}catch(e){alert('发送失败');}
    this.disabled=false;this.textContent='发送验证码';
});
</script>
@endsection
