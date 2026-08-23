<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '用户中心') — 91VPN</title>
    <link rel="stylesheet" href="/css/malio.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">91VPN<small>用户中心</small></div>
        <ul class="nav">
            <li><a href="/user" class="{{ request()->is('user') ? 'active' : '' }}"><span class="ic">🏠</span>首页</a></li>
            <li><a href="/user/shop" class="{{ request()->is('user/shop') ? 'active' : '' }}"><span class="ic">🛒</span>商店</a></li>
            <li class="group">我的</li>
            <li><a href="/user/wallet" class="{{ request()->is('user/wallet') ? 'active' : '' }}"><span class="ic">💰</span>我的钱包</a></li>
            <li><a href="/user/invite" class="{{ request()->is('user/invite') ? 'active' : '' }}"><span class="ic">🎁</span>邀请返利</a></li>
            <li class="group">使用</li>
            <li><a href="/user/node" class="{{ request()->is('user/node') ? 'active' : '' }}"><span class="ic">🔗</span>节点设置</a></li>
            <li><a href="/user/ticket" class="{{ request()->is('user/ticket*') ? 'active' : '' }}"><span class="ic">💬</span>工单支持</a></li>
            <li><a href="/user/announcement" class="{{ request()->is('user/announcement') ? 'active' : '' }}"><span class="ic">📢</span>公告</a></li>
        </ul>
    </aside>
    <main class="main">
        <div class="topbar">
            <div class="page-title">@yield('title', '用户中心')</div>
            <div>
                <span class="hi">Hi, {{ auth()->user()->name }}</span>
                <form method="POST" action="/logout" style="display:inline;margin-left:12px">@csrf<button class="btn ghost sm">退出登录</button></form>
            </div>
        </div>
        @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
