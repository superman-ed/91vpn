<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '管理后台') — 91VPN</title>
    <link rel="stylesheet" href="/css/malio.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">91VPN<small>管理后台</small></div>
        <ul class="nav">
            <li><a href="/admin" class="{{ request()->is('admin') ? 'active' : '' }}"><span class="ic">📊</span>概览</a></li>
            <li class="group">运营</li>
            <li><a href="/admin/users" class="{{ request()->is('admin/users*') ? 'active' : '' }}"><span class="ic">👥</span>用户管理</a></li>
            <li><a href="/admin/nodes" class="{{ request()->is('admin/nodes*') ? 'active' : '' }}"><span class="ic">🖧</span>节点管理</a></li>
            <li><a href="/admin/plans" class="{{ request()->is('admin/plans*') ? 'active' : '' }}"><span class="ic">📦</span>套餐管理</a></li>
            <li><a href="/admin/orders" class="{{ request()->is('admin/orders*') ? 'active' : '' }}"><span class="ic">🧾</span>订单管理</a></li>
            <li class="group">支持</li>
            <li><a href="/admin/tickets" class="{{ request()->is('admin/tickets*') ? 'active' : '' }}"><span class="ic">💬</span>工单管理</a></li>
            <li><a href="/admin/coupons" class="{{ request()->is('admin/coupons*') ? 'active' : '' }}"><span class="ic">🎟️</span>优惠券</a></li>
            <li><a href="/admin/announcements" class="{{ request()->is('admin/announcements*') ? 'active' : '' }}"><span class="ic">📢</span>公告管理</a></li>
            <li class="group"></li>
            <li><a href="/user"><span class="ic">←</span>返回用户端</a></li>
        </ul>
    </aside>
    <main class="main">
        <div class="topbar">
            <div class="page-title">@yield('title', '管理后台')</div>
            <form method="POST" action="/logout">@csrf<button class="btn ghost sm">退出</button></form>
        </div>
        @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
