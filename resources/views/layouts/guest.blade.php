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
    </style>
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo"><i class="fas fa-shield-halved"></i>91VPN</div>
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
<script src="/stisla/assets/modules/jquery.min.js"></script>
<script src="/stisla/assets/modules/popper.js"></script>
<script src="/stisla/assets/modules/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
