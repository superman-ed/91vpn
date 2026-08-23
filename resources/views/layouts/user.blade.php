<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', '用户中心') — 91VPN</title>
    <link rel="stylesheet" href="/stisla/assets/modules/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/stisla/assets/modules/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/stisla/assets/css/style.css">
    <link rel="stylesheet" href="/stisla/assets/css/components.css">
    <meta name="turbo-prefetch" content="true">
    <script src="/js/turbo.min.js" defer></script>
</head>
<body>
<div id="app">
    <div class="main-wrapper main-wrapper-1">
        <div class="navbar-bg"></div>
        <nav class="navbar navbar-expand-lg main-navbar">
            <form class="form-inline mr-auto">
                <ul class="navbar-nav mr-3">
                    <li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg collapse-btn"><i class="fas fa-bars"></i></a></li>
                </ul>
            </form>
            <ul class="navbar-nav navbar-right">
                <li class="dropdown"><a href="#" class="nav-link nav-link-lg nav-link-user">
                    <span class="d-sm-none d-lg-inline-block">Hi, {{ auth()->user()->name }}</span></a></li>
                <li>
                    <form method="POST" action="/logout" class="ml-2">@csrf
                        <button class="btn btn-outline-primary btn-sm">退出登录</button>
                    </form>
                </li>
            </ul>
        </nav>

        <div class="main-sidebar sidebar-style-2">
            <aside id="sidebar-wrapper">
                <div class="sidebar-brand"><a href="/user">91VPN</a></div>
                <div class="sidebar-brand sidebar-brand-sm"><a href="/user">9V</a></div>
                <ul class="sidebar-menu">
                    <li class="menu-header">主菜单</li>
                    <li class="{{ request()->is('user') ? 'active' : '' }}"><a class="nav-link" href="/user"><i class="fas fa-home"></i><span>首页</span></a></li>
                    <li class="{{ request()->is('user/shop') ? 'active' : '' }}"><a class="nav-link" href="/user/shop"><i class="fas fa-shopping-cart"></i><span>商店</span></a></li>
                    <li class="menu-header">我的</li>
                    <li class="{{ request()->is('user/wallet') ? 'active' : '' }}"><a class="nav-link" href="/user/wallet"><i class="fas fa-wallet"></i><span>我的钱包</span></a></li>
                    <li class="{{ request()->is('user/invite') ? 'active' : '' }}"><a class="nav-link" href="/user/invite"><i class="fas fa-gift"></i><span>邀请返利</span></a></li>
                    <li class="{{ request()->is('user/account') ? 'active' : '' }}"><a class="nav-link" href="/user/account"><i class="fas fa-user-cog"></i><span>账号设置</span></a></li>
                    <li class="menu-header">使用</li>
                    <li class="{{ request()->is('user/servers') ? 'active' : '' }}"><a class="nav-link" href="/user/servers"><i class="fas fa-server"></i><span>节点列表</span></a></li>
                    <li class="{{ request()->is('user/node') ? 'active' : '' }}"><a class="nav-link" href="/user/node"><i class="fas fa-link"></i><span>节点设置</span></a></li>
                    <li class="{{ request()->is('user/traffic') ? 'active' : '' }}"><a class="nav-link" href="/user/traffic"><i class="fas fa-chart-line"></i><span>流量明细</span></a></li>
                    <li class="{{ request()->is('user/ticket*') ? 'active' : '' }}"><a class="nav-link" href="/user/ticket"><i class="far fa-comments"></i><span>工单支持</span></a></li>
                    <li class="{{ request()->is('user/announcement') ? 'active' : '' }}"><a class="nav-link" href="/user/announcement"><i class="fas fa-bullhorn"></i><span>公告</span></a></li>
                </ul>
            </aside>
        </div>

        <div class="main-content">
            <section class="section">
                <div class="section-header"><h1>@yield('title', '用户中心')</h1></div>
                <div class="section-body">
                    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                    @if ($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
                    @yield('content')
                </div>
            </section>
        </div>
        <footer class="main-footer">
            <div class="footer-left">91VPN © {{ date('Y') }}</div>
        </footer>
    </div>
</div>
<script src="/stisla/assets/modules/jquery.min.js"></script>
<script src="/stisla/assets/modules/popper.js"></script>
<script src="/stisla/assets/modules/tooltip.js"></script>
<script src="/stisla/assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="/stisla/assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="/stisla/assets/modules/moment.min.js"></script>
<script src="/stisla/assets/js/stisla.js"></script>
<script src="/stisla/assets/js/scripts.js"></script>
<script src="/stisla/assets/js/custom.js"></script>
</body>
</html>
