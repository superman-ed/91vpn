<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '用户中心') — 91VPN</title>
    <style>
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f3f6f8;color:#34395e}
        .layout{display:flex;min-height:100vh}
        .sidebar{width:220px;background:#fff;border-right:1px solid #e3eaef;padding:20px 0;flex-shrink:0}
        .brand{font-size:20px;font-weight:700;color:#6777ef;padding:0 24px 20px;border-bottom:1px solid #f2f2f2}
        .nav{list-style:none;padding:0;margin:16px 0}
        .nav a{display:block;padding:11px 24px;color:#6c757d;text-decoration:none;font-size:14px}
        .nav a:hover,.nav a.active{background:#f9fafe;color:#6777ef;border-right:3px solid #6777ef}
        .nav .group{padding:14px 24px 6px;font-size:11px;color:#acb5c9;text-transform:uppercase;letter-spacing:1px}
        .main{flex:1;padding:24px 32px}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .topbar .hi{font-size:15px}
        .topbar form{display:inline}
        .btn{padding:7px 14px;background:#6777ef;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;text-decoration:none}
        .btn.ghost{background:#fff;color:#6777ef;border:1px solid #e3eaef}
        .btn.danger{background:#fc544b}
        .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px}
        .card{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.04);padding:20px}
        .card .k{font-size:13px;color:#6c757d;margin-bottom:8px}
        .card .v{font-size:26px;font-weight:600}
        .card .v small{font-size:14px;color:#6c757d;font-weight:400}
        .card .sub{font-size:12px;color:#acb5c9;margin-top:6px}
        .panel{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.04);padding:24px;margin-bottom:20px}
        .panel h3{margin:0 0 16px;font-size:16px}
        .flash{background:#effff4;color:#2fae66;padding:10px 16px;border-radius:6px;margin-bottom:16px;font-size:14px}
        .err{background:#fff0f0;color:#fc544b;padding:10px 16px;border-radius:6px;margin-bottom:16px;font-size:14px}
        input,textarea{padding:9px 12px;border:1px solid #e3eaef;border-radius:6px;font-size:14px;font-family:inherit}
        code{background:#f2f2f2;padding:3px 8px;border-radius:4px;font-size:13px;word-break:break-all}
        table{width:100%;border-collapse:collapse;font-size:14px}
        th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #f2f2f2}
        th{color:#6c757d;font-weight:500;font-size:13px}
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">91VPN</div>
        <ul class="nav">
            <li><a href="/user" class="{{ request()->is('user') ? 'active' : '' }}">首页</a></li>
            <li><a href="/user/shop" class="{{ request()->is('user/shop') ? 'active' : '' }}">商店</a></li>
            <li class="group">我的</li>
            <li><a href="/user/wallet" class="{{ request()->is('user/wallet') ? 'active' : '' }}">我的钱包</a></li>
            <li><a href="/user/invite" class="{{ request()->is('user/invite') ? 'active' : '' }}">邀请注册</a></li>
            <li class="group">使用</li>
            <li><a href="/user/node" class="{{ request()->is('user/node') ? 'active' : '' }}">节点设置</a></li>
            <li><a href="/user/announcement" class="{{ request()->is('user/announcement') ? 'active' : '' }}">公告</a></li>
        </ul>
    </aside>
    <main class="main">
        <div class="topbar">
            <div class="hi">Hi, {{ auth()->user()->name }}</div>
            <form method="POST" action="/logout">@csrf<button class="btn ghost">退出登录</button></form>
        </div>
        @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
