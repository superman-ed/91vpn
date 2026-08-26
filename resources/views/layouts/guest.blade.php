<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', '账户') — 91VPN</title>
    <link rel="stylesheet" href="/stisla/assets/modules/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/stisla/assets/modules/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/stisla/assets/css/style.css">
    <link rel="stylesheet" href="/stisla/assets/css/components.css">
    <meta name="turbo-prefetch" content="true">
    <script src="/js/turbo.min.js" defer></script>
    <style>
        body.auth-body { min-height: 100vh; margin: 0; background: linear-gradient(135deg, #eef1ff 0%, #f6f7fb 45%, #eaf6ff 100%); display: flex; align-items: center; justify-content: center; padding: 24px 16px; font-family: 'Nunito', -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; }
        .auth-wrap { width: 100%; max-width: 440px; }
        .auth-card { background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 20px 50px rgba(103,119,239,.20); }
        .auth-brand { background: linear-gradient(135deg, #6777ef 0%, #5a67e8 55%, #7c4ddb 100%); color: #fff; text-align: center; padding: 30px 24px 26px; position: relative; }
        .auth-brand .logo { font-size: 30px; font-weight: 800; letter-spacing: 1px; }
        .auth-brand .logo i { margin-right: 6px; }
        .auth-brand .tagline { font-size: 13px; opacity: .9; margin-top: 4px; letter-spacing: 2px; }
        .auth-inner { padding: 28px 30px 30px; }
        .auth-inner .auth-title { font-size: 18px; font-weight: 700; color: #34395e; margin: 0 0 18px; }
        .auth-inner label { font-size: 13px; color: #7a869a; font-weight: 600; margin-bottom: 5px; }
        .auth-inner .input-group-text { background: #f6f7fb; border-color: #eef0f5; color: #98a6ad; border-radius: 10px 0 0 10px; }
        .auth-inner .form-control { border-color: #eef0f5; border-radius: 10px; height: auto; padding: 11px 14px; }
        .auth-inner .input-group .form-control { border-radius: 0 10px 10px 0; }
        .auth-inner .form-control:focus { border-color: #6777ef; box-shadow: 0 0 0 3px rgba(103,119,239,.12); }
        .auth-inner .btn-auth { background: linear-gradient(135deg, #6777ef, #5a67e8); border: none; border-radius: 10px; font-weight: 700; padding: 12px; color: #fff; }
        .auth-inner .btn-auth:hover { filter: brightness(1.05); color: #fff; }
        .auth-links { text-align: center; font-size: 13.5px; color: #7a869a; margin-top: 4px; }
        .auth-links a { color: #6777ef; font-weight: 600; }
        .auth-foot { text-align: center; color: #b0bac5; font-size: 12px; margin-top: 18px; }
        .auth-inner .alert { border-radius: 10px; font-size: 13.5px; }
        .auth-toast { position: fixed; top: 22px; left: 50%; transform: translateX(-50%) translateY(-14px); z-index: 9999;
            padding: 11px 20px; border-radius: 11px; font-size: 14px; font-weight: 600; color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,.16);
            opacity: 0; pointer-events: none; transition: opacity .22s, transform .22s; max-width: 90vw; }
        .auth-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .auth-toast.ok { background: linear-gradient(135deg, #47c363, #3aae55); }
        .auth-toast.warn { background: linear-gradient(135deg, #fc784b, #f36c3d); }
    </style>
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo"><i class="fas fa-shield-alt"></i>91VPN</div>
            <div class="tagline">安全 · 稳定 · 高速</div>
        </div>
        <div class="auth-inner">
            <div class="auth-title">@yield('title', '账户')</div>
            @if ($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @yield('content')
        </div>
    </div>
    <div class="auth-foot">91VPN © {{ date('Y') }}</div>
</div>
<div class="auth-toast" id="authToast"></div>
<script src="/stisla/assets/modules/jquery.min.js"></script>
<script src="/stisla/assets/modules/popper.js"></script>
<script src="/stisla/assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script>
// 轻量顶部提示,替代 alert();type: ok | warn
window.authToast = function(msg, type){
    var el = document.getElementById('authToast');
    if(!el) return;
    el.className = 'auth-toast ' + (type === 'warn' ? 'warn' : 'ok');
    el.textContent = msg;
    void el.offsetWidth;               // 触发重绘,保证 transition
    el.classList.add('show');
    clearTimeout(window.__authToastT);
    window.__authToastT = setTimeout(function(){ el.classList.remove('show'); }, 3200);
};

// 统一的"发送验证码"处理:按钮标 data-send-code data-endpoint,含 60 秒倒计时冷却
// (收敛注册/找回两页原本各写一份的近似脚本)
document.querySelectorAll('button[data-send-code]').forEach(function(btn){
    var label = btn.textContent;
    btn.addEventListener('click', async function(){
        var input = document.querySelector('input[name=email]');
        var email = input ? input.value.trim() : '';
        if(!email){ authToast('请先填写邮箱','warn'); return; }
        btn.disabled = true; btn.textContent = '发送中…';
        try {
            var r = await fetch(btn.dataset.endpoint, {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                body: JSON.stringify({email: email}),
            });
            var j = await r.json();
            authToast(j.message || '验证码已发送', r.ok ? 'ok' : 'warn');
            if(r.ok){
                var s = 60;
                var t = setInterval(function(){ btn.textContent = s + ' 秒'; if(--s < 0){ clearInterval(t); btn.disabled = false; btn.textContent = label; } }, 1000);
                btn.textContent = s + ' 秒';
            } else { btn.disabled = false; btn.textContent = label; }
        } catch(e){ authToast('发送失败，请稍后重试','warn'); btn.disabled = false; btn.textContent = label; }
    });
});
</script>
</body>
</html>
