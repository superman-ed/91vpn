<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', '管理后台') — 91VPN</title>
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
                <ul class="navbar-nav mr-3"><li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg collapse-btn"><i class="fas fa-bars"></i></a></li></ul>
            </form>
            <ul class="navbar-nav navbar-right">
                <li class="dropdown"><a href="#" class="nav-link nav-link-lg"><span class="badge badge-primary">管理员</span></a></li>
                <li><form method="POST" action="/logout" class="ml-2">@csrf<button class="btn btn-outline-primary btn-sm">退出</button></form></li>
            </ul>
        </nav>
        <div class="main-sidebar sidebar-style-2">
            <aside id="sidebar-wrapper">
                <div class="sidebar-brand"><a href="/admin">91VPN 后台</a></div>
                <div class="sidebar-brand sidebar-brand-sm"><a href="/admin">9V</a></div>
                <ul class="sidebar-menu">
                    <li class="{{ request()->is('admin') ? 'active' : '' }}"><a class="nav-link" href="/admin"><i class="fas fa-chart-bar"></i><span>概览</span></a></li>
                    <li class="menu-header">运营</li>
                    <li class="{{ request()->is('admin/users*') ? 'active' : '' }}"><a class="nav-link" href="/admin/users"><i class="fas fa-users"></i><span>用户管理</span></a></li>
                    <li class="{{ request()->is('admin/nodes*') ? 'active' : '' }}"><a class="nav-link" href="/admin/nodes"><i class="fas fa-server"></i><span>节点管理</span></a></li>
                    <li class="{{ request()->is('admin/plans*') ? 'active' : '' }}"><a class="nav-link" href="/admin/plans"><i class="fas fa-box"></i><span>套餐管理</span></a></li>
                    <li class="{{ request()->is('admin/orders*') ? 'active' : '' }}"><a class="nav-link" href="/admin/orders"><i class="fas fa-receipt"></i><span>订单管理</span></a></li>
                    <li class="menu-header">支持</li>
                    <li class="{{ request()->is('admin/tickets*') ? 'active' : '' }}"><a class="nav-link" href="/admin/tickets"><i class="far fa-comments"></i><span>工单管理</span></a></li>
                    <li class="{{ request()->is('admin/coupons*') ? 'active' : '' }}"><a class="nav-link" href="/admin/coupons"><i class="fas fa-ticket-alt"></i><span>优惠券</span></a></li>
                    <li class="{{ request()->is('admin/announcements*') ? 'active' : '' }}"><a class="nav-link" href="/admin/announcements"><i class="fas fa-bullhorn"></i><span>公告管理</span></a></li>
                    <li class="menu-header"></li>
                    <li><a class="nav-link" href="/user"><i class="fas fa-arrow-left"></i><span>返回用户端</span></a></li>
                </ul>
            </aside>
        </div>
        <div class="main-content">
            <section class="section">
                <div class="section-header"><h1>@yield('title', '管理后台')</h1></div>
                <div class="section-body">
                    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                    @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
                    @yield('content')
                </div>
            </section>
        </div>
        <footer class="main-footer"><div class="footer-left">91VPN 管理后台 © {{ date('Y') }}</div></footer>
    </div>
</div>
<script src="/stisla/assets/modules/jquery.min.js"></script>
<script src="/stisla/assets/modules/popper.js"></script>
<script src="/stisla/assets/modules/tooltip.js"></script>
<script src="/stisla/assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="/stisla/assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="/stisla/assets/js/stisla.js"></script>
<script src="/stisla/assets/js/scripts.js"></script>
<script src="/stisla/assets/js/custom.js"></script>
</body>
</html>
