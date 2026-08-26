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
(function(){
    const btn=document.getElementById('sendReset');
    const label='发送验证码';
    let timer=null;
    function cooldown(s){
        btn.disabled=true;
        timer=setInterval(function(){
            btn.textContent=s+' 秒';
            if(--s<0){clearInterval(timer);btn.disabled=false;btn.textContent=label;}
        },1000);
        btn.textContent=s+' 秒';
    }
    btn.addEventListener('click', async function(){
        const email=document.querySelector('input[name=email]').value.trim();
        if(!email){authToast('请先填写邮箱','warn');return;}
        btn.disabled=true;btn.textContent='发送中…';
        try{
            const r=await fetch('/password/send',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({email})});
            const j=await r.json();
            authToast(j.message||'若邮箱已注册，验证码已发送', r.ok?'ok':'warn');
            if(r.ok){cooldown(60);}else{btn.disabled=false;btn.textContent=label;}
        }catch(e){authToast('发送失败，请稍后重试','warn');btn.disabled=false;btn.textContent=label;}
    });
})();
</script>
@endsection
