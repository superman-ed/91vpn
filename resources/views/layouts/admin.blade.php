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
    <style>
        /* 后台通用精美样式：所有列表页复用 */
        .adm-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .adm-head h4 { font-size: 18px; font-weight: 700; color: #34395e; margin: 0; }
        .adm-tools { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .adm-search .form-control { border-radius: 9px; border-color: #eef0f5; }
        .adm-btn { border-radius: 9px; font-weight: 600; background: linear-gradient(135deg,#6777ef,#5a67e8); border: none; color: #fff; }
        .adm-btn:hover { filter: brightness(1.05); color: #fff; }
        .adm-panel { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; }
        .adm-panel .card-body { padding: 0; }
        .adm-table { margin: 0; }
        .adm-table thead th { border: none; background: #fafbff; color: #98a6ad; font-size: 12px; font-weight: 600; padding: 12px 20px; white-space: nowrap; }
        .adm-table tbody td { border-top: 1px solid #f4f6fb; padding: 13px 20px; font-size: 13.5px; color: #54667a; vertical-align: middle; }
        .adm-table tbody tr:hover { background: #fafbff; }
        .adm-table .btn-sm { border-radius: 7px; }
        .adm-pill { padding: 4px 11px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .adm-pill.ok { background: #e9f9ed; color: #2fa84f; }
        .adm-pill.warn { background: #fff5e6; color: #e6912a; }
        .adm-pill.danger { background: #fdecea; color: #fc544b; }
        .adm-pill.info { background: #e7f3ff; color: #3a8ee6; }
        .adm-pill.primary { background: #eef0ff; color: #6777ef; }
        .adm-pill.muted { background: #f2f3f5; color: #98a6ad; }
        .adm-foot { padding: 14px 20px; border-top: 1px solid #f4f6fb; }
        .adm-foot .pagination { margin: 0; justify-content: flex-end; }
        .adm-foot .page-item.active .page-link { background: #6777ef; border-color: #6777ef; }
        .adm-foot .page-link { color: #6777ef; border-radius: 7px; margin: 0 2px; border-color: #eef0f5; }
        .adm-empty { text-align: center; color: #98a6ad; padding: 44px 0; }
        /* 后台表单页通用 */
        .adm-form-card { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; margin-bottom: 20px; }
        .adm-form-card .card-header { border-bottom: 1px solid #f1f3fb; padding: 15px 22px; display: flex; align-items: center; gap: 9px; }
        .adm-form-card .card-header .ic { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; background: linear-gradient(135deg,#6777ef,#5a67e8); }
        .adm-form-card .card-header h4 { font-size: 15px; font-weight: 700; color: #34395e; margin: 0; }
        .adm-form-card .card-body { padding: 22px; }
        .adm-form-card .form-tip { font-size: 12.5px; color: #98a6ad; margin: -6px 0 14px; }
        .adm-form label { font-size: 13px; color: #7a869a; font-weight: 600; margin-bottom: 4px; }
        .adm-form .form-control, .adm-form select.form-control, .adm-form textarea { border-radius: 9px; border-color: #eef0f5; }
        .adm-form .form-control:focus { border-color: #6777ef; box-shadow: 0 0 0 3px rgba(103,119,239,.12); }
    </style>
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
                    <li class="{{ request()->is('admin/finance*') ? 'active' : '' }}"><a class="nav-link" href="/admin/finance"><i class="fas fa-money-bill-wave"></i><span>资金流水</span></a></li>
                    <li class="{{ request()->is('admin/rebates*') ? 'active' : '' }}"><a class="nav-link" href="/admin/rebates"><i class="fas fa-hand-holding-usd"></i><span>返佣记录</span></a></li>
                    <li class="{{ request()->is('admin/promo*') ? 'active' : '' }}"><a class="nav-link" href="/admin/promo"><i class="fas fa-bullhorn"></i><span>推广代理</span></a></li>
                    <li class="{{ request()->is('admin/online*') ? 'active' : '' }}"><a class="nav-link" href="/admin/online"><i class="fas fa-signal"></i><span>在线用户</span></a></li>
                    <li class="menu-header">支持</li>
                    <li class="{{ request()->is('admin/tickets*') ? 'active' : '' }}"><a class="nav-link" href="/admin/tickets"><i class="far fa-comments"></i><span>工单管理</span></a></li>
                    <li class="{{ request()->is('admin/coupons*') ? 'active' : '' }}"><a class="nav-link" href="/admin/coupons"><i class="fas fa-ticket-alt"></i><span>优惠券</span></a></li>
                    <li class="{{ request()->is('admin/announcements*') ? 'active' : '' }}"><a class="nav-link" href="/admin/announcements"><i class="fas fa-bullhorn"></i><span>公告管理</span></a></li>
                    <li class="{{ request()->is('admin/admins*') ? 'active' : '' }}"><a class="nav-link" href="/admin/admins"><i class="fas fa-user-shield"></i><span>管理员</span></a></li>
                    <li class="{{ request()->is('admin/settings*') ? 'active' : '' }}"><a class="nav-link" href="/admin/settings"><i class="fas fa-cog"></i><span>站点设置</span></a></li>
                    <li class="menu-header">系统</li>
                    <li class="{{ request()->is('admin/system/login-logs*') ? 'active' : '' }}"><a class="nav-link" href="/admin/system/login-logs"><i class="fas fa-sign-in-alt"></i><span>登录日志</span></a></li>
                    <li class="{{ request()->is('admin/system/devices*') ? 'active' : '' }}"><a class="nav-link" href="/admin/system/devices"><i class="fas fa-mobile-alt"></i><span>设备统计</span></a></li>
                    <li class="{{ request()->is('admin/system/acquisition*') ? 'active' : '' }}"><a class="nav-link" href="/admin/system/acquisition"><i class="fas fa-route"></i><span>来路统计</span></a></li>
                    <li class="{{ request()->is('admin/system/audit*') ? 'active' : '' }}"><a class="nav-link" href="/admin/system/audit"><i class="fas fa-clipboard-list"></i><span>操作日志</span></a></li>
                    <li class="{{ request()->is('admin/system/emails*') ? 'active' : '' }}"><a class="nav-link" href="/admin/system/emails"><i class="fas fa-envelope-open-text"></i><span>邮件记录</span></a></li>
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

{{-- 统一危险操作确认组件：form 上加 data-confirm="提示"，高危再加 data-confirm-word="需输入的词" --}}
<div id="dcOverlay" style="display:none;position:fixed;inset:0;background:rgba(20,24,40,.5);z-index:1200;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:14px;width:440px;max-width:92vw;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px"><span style="width:38px;height:38px;border-radius:11px;background:#fdecea;color:#fc544b;display:flex;align-items:center;justify-content:center;font-size:18px"><i class="fas fa-exclamation-triangle"></i></span><h5 id="dcTitle" style="font-weight:700;color:#34395e;margin:0">确认操作</h5></div>
        <div id="dcMsg" style="font-size:13.5px;color:#54667a;line-height:1.6;margin-bottom:14px"></div>
        <div id="dcInputWrap" style="display:none;margin-bottom:14px">
            <div style="font-size:12.5px;color:#98a6ad;margin-bottom:6px">请输入 <b id="dcWord" style="color:#fc544b;font-family:monospace"></b> 以确认：</div>
            <input id="dcInput" class="form-control" autocomplete="off" style="border-radius:9px;font-family:monospace">
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" id="dcCancel" class="btn btn-light" style="border-radius:9px">取消</button>
            <button type="button" id="dcOk" class="btn btn-danger" style="border-radius:9px">确认执行</button>
        </div>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('dcOverlay'), pending = null;
    var elMsg = document.getElementById('dcMsg'), elTitle = document.getElementById('dcTitle');
    var wrap = document.getElementById('dcInputWrap'), input = document.getElementById('dcInput'), elWord = document.getElementById('dcWord');
    var ok = document.getElementById('dcOk'), cancel = document.getElementById('dcCancel');

    function close() { overlay.style.display = 'none'; pending = null; input.value = ''; }
    function open(form) {
        pending = form;
        elMsg.textContent = form.getAttribute('data-confirm') || '确认执行该操作？';
        elTitle.textContent = form.getAttribute('data-confirm-title') || '危险操作确认';
        var word = form.getAttribute('data-confirm-word');
        if (word) {
            wrap.style.display = 'block'; elWord.textContent = word;
            ok.disabled = true; setTimeout(function () { input.focus(); }, 50);
            input.oninput = function () { ok.disabled = input.value.trim() !== word; };
        } else {
            wrap.style.display = 'none'; ok.disabled = false; input.oninput = null;
        }
        overlay.style.display = 'flex';
    }
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.matches && f.matches('form[data-confirm]') && !f.__dcPassed) {
            e.preventDefault(); open(f);
        }
    }, true);
    ok.addEventListener('click', function () {
        if (ok.disabled || !pending) return;
        var f = pending; f.__dcPassed = true; close(); f.submit();
    });
    cancel.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
})();
</script>
</body>
</html>
