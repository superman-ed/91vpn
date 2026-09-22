<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', '账户') — 91VPN</title>
    <link rel="icon" type="image/jpeg" href="/og.jpg">
    <link rel="stylesheet" href="/stisla/assets/modules/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/stisla/assets/modules/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/stisla/assets/css/style.css">
    <link rel="stylesheet" href="/stisla/assets/css/components.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=JetBrains+Mono:wght@400;700&family=Noto+Sans+SC:wght@400;700&display=swap" rel="stylesheet">
    <meta name="turbo-prefetch" content="true">
    <script src="/js/turbo.min.js" defer></script>
    <style>
        :root{--paper:#F1EEE4;--card:#F7F4EC;--ink:#1A160F;--dim:#5E5849;--faint:#928B78;--rule:#C4BCA8;--sig:#D8442A;--sig2:#EA4E2E;
            --sign:"Archivo","Noto Sans SC",sans-serif;--mono:"JetBrains Mono",ui-monospace,monospace;--body:"Noto Sans SC","Archivo",sans-serif}
        body.auth-body{min-height:100vh;margin:0;background:var(--paper);color:var(--ink);display:flex;align-items:center;justify-content:center;padding:24px 16px;font-family:var(--body)}
        .auth-wrap{width:100%;max-width:400px}
        .auth-card{background:var(--card);border:1px solid var(--rule);border-radius:4px;overflow:hidden}
        .auth-brand{border-bottom:2px solid var(--ink);padding:26px 28px 18px;position:relative}
        .auth-brand::before{content:"";position:absolute;top:0;left:0;right:0;height:5px;background:var(--sig)}
        .auth-brand .logo{font-family:var(--sign);font-weight:900;font-size:26px;letter-spacing:-.01em;color:var(--ink);display:flex;align-items:center;gap:9px}
        .auth-brand .logo .sq{width:14px;height:14px;background:var(--sig)}
        .auth-brand .logo .vpn{color:var(--sig)}
        .auth-brand .tagline{font-family:var(--mono);font-size:11px;letter-spacing:.18em;color:var(--faint);margin-top:8px;text-transform:uppercase}
        .auth-inner{padding:24px 28px 28px}
        .auth-inner .auth-title{font-family:var(--sign);font-size:17px;font-weight:800;color:var(--ink);margin:0 0 18px}
        .auth-inner label{font-family:var(--mono);font-size:11px;letter-spacing:.08em;color:var(--faint);font-weight:400;margin-bottom:6px;text-transform:uppercase}
        .auth-inner .form-control{border:1px solid var(--rule);border-radius:3px;height:auto;padding:11px 13px;background:#FCFAF4;color:var(--ink);font-family:var(--body)}
        .auth-inner .input-group .form-control{border-radius:0 3px 3px 0}
        .auth-inner .form-control:focus{border-color:var(--sig);box-shadow:0 0 0 3px rgba(216,68,42,.12);background:#fff}
        .auth-inner .input-group-text{background:var(--paper);border:1px solid var(--rule);color:var(--faint);border-radius:3px 0 0 3px}
        .auth-inner .btn-auth{background:var(--sig);border:1px solid var(--sig);border-radius:3px;font-family:var(--sign);font-weight:800;padding:12px;color:#FBF6EC;letter-spacing:.02em}
        .auth-inner .btn-auth:hover{background:var(--sig2);color:#FBF6EC}
        .auth-links{text-align:center;font-size:13.5px;color:var(--dim);margin-top:6px}
        .auth-links a{color:var(--sig);font-weight:700}
        .auth-foot{text-align:center;color:var(--faint);font-family:var(--mono);font-size:11.5px;letter-spacing:.05em;margin-top:16px}
        .auth-inner .alert{border-radius:3px;font-size:13.5px;border:1px solid}
        .auth-inner .alert-danger{background:#fbe9e5;border-color:#e7b3a6;color:#8a2c17}
        .auth-inner .alert-success{background:#e7f2ea;border-color:#a9d3b7;color:#245c39}
        .auth-inner .custom-control-label{font-family:var(--body)}
        .auth-toast{position:fixed;top:22px;left:50%;transform:translateX(-50%) translateY(-14px);z-index:9999;
            padding:11px 20px;border-radius:3px;font-family:var(--mono);font-size:13px;font-weight:700;color:#FBF6EC;
            opacity:0;pointer-events:none;transition:opacity .22s,transform .22s;max-width:90vw}
        .auth-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
        .auth-toast.ok{background:var(--ink)}
        .auth-toast.warn{background:var(--sig)}
    </style>
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <a href="/" class="logo" style="text-decoration:none"><span class="sq"></span>91<span class="vpn">VPN</span></a>
            <div class="tagline">Global Access · 全球加速</div>
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
{{-- `[!!]` 客服挂件必须在【未登录】的页面上。站在登录页却进不去的人，正是最需要
     联系客服的那个 —— 而工单要登录，那是个死循环（L-20）。
     `[D]` 9eba712 加了这一行并配了守卫测试；93c343a 重做样式时把它删了，
     而那条测试没有当场变红（最可能是编译后的视图缓存没失效）。
     删它之前先想清楚：登不进去的人还剩哪条路。 --}}
@include('partials.support')
@yield('scripts')
</body>
</html>
