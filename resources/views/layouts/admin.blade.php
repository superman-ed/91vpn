<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '管理后台') — 91VPN</title>
    <style>
        *{box-sizing:border-box}body{font-family:system-ui,sans-serif;margin:0;background:#f3f6f8;color:#34395e}
        .layout{display:flex;min-height:100vh}
        .sidebar{width:200px;background:#191d21;padding:20px 0;flex-shrink:0}
        .brand{font-size:18px;font-weight:700;color:#fff;padding:0 20px 18px}
        .brand small{color:#63ed7a;font-size:11px;display:block}
        .nav{list-style:none;padding:0;margin:10px 0}
        .nav a{display:block;padding:11px 20px;color:#9aa0ac;text-decoration:none;font-size:14px}
        .nav a:hover,.nav a.active{background:#0d0f11;color:#fff}
        .main{flex:1;padding:24px 32px}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .btn{padding:7px 14px;background:#6777ef;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;text-decoration:none;display:inline-block}
        .btn.ghost{background:#fff;color:#6777ef;border:1px solid #e3eaef}
        .btn.danger{background:#fc544b}.btn.sm{padding:4px 10px;font-size:12px}
        .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
        .card{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.04);padding:20px}
        .card .k{font-size:13px;color:#6c757d}.card .v{font-size:26px;font-weight:600;margin-top:6px}
        .panel{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.04);padding:24px;margin-bottom:20px}
        .panel h3{margin:0 0 16px;font-size:16px;display:flex;justify-content:space-between;align-items:center}
        .flash{background:#effff4;color:#2fae66;padding:10px 16px;border-radius:6px;margin-bottom:16px}
        .err{background:#fff0f0;color:#fc544b;padding:10px 16px;border-radius:6px;margin-bottom:16px}
        table{width:100%;border-collapse:collapse;font-size:14px}
        th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #f2f2f2}
        th{color:#6c757d;font-weight:500;font-size:13px}
        input,select,textarea{padding:8px 10px;border:1px solid #e3eaef;border-radius:6px;font-size:14px;font-family:inherit}
        label{display:block;font-size:13px;color:#6c757d;margin:10px 0 4px}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    </style>
</head>
<body><div class="layout">
    <aside class="sidebar">
        <div class="brand">91VPN<small>管理后台</small></div>
        <ul class="nav">
            <li><a href="/admin" class="{{ request()->is('admin') ? 'active' : '' }}">概览</a></li>
            <li><a href="/admin/users" class="{{ request()->is('admin/users*') ? 'active' : '' }}">用户管理</a></li>
            <li><a href="/admin/nodes" class="{{ request()->is('admin/nodes*') ? 'active' : '' }}">节点管理</a></li>
            <li><a href="/admin/plans" class="{{ request()->is('admin/plans*') ? 'active' : '' }}">套餐管理</a></li>
            <li><a href="/admin/orders" class="{{ request()->is('admin/orders*') ? 'active' : '' }}">订单管理</a></li>
            <li><a href="/admin/tickets" class="{{ request()->is('admin/tickets*') ? 'active' : '' }}">工单管理</a></li>
            <li><a href="/admin/coupons" class="{{ request()->is('admin/coupons*') ? 'active' : '' }}">优惠券</a></li>
            <li><a href="/admin/announcements" class="{{ request()->is('admin/announcements*') ? 'active' : '' }}">公告管理</a></li>
            <li><a href="/user" style="color:#63ed7a">← 返回用户端</a></li>
        </ul>
    </aside>
    <main class="main">
        <div class="topbar">
            <div>@yield('title', '管理后台')</div>
            <form method="POST" action="/logout">@csrf<button class="btn ghost">退出</button></form>
        </div>
        @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        @yield('content')
    </main>
</div></body></html>
