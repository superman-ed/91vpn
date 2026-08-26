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
    <meta name="turbo-cache-control" content="no-cache">
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
                    <li class="{{ request()->is('admin/system/health*') ? 'active' : '' }}"><a class="nav-link" href="/admin/system/health"><i class="fas fa-heartbeat"></i><span>系统健康</span></a></li>
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

{{-- 统一危险操作确认组件：form 上加 data-dgr="提示"，高危再加 data-dgr-word="需输入的词" --}}
<style>
    .dc-overlay { position:fixed; inset:0; background:rgba(16,20,35,.55); backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); z-index:1200; align-items:center; justify-content:center; animation:dcFade .2s ease; }
    .dc-card { background:#fff; border-radius:20px; width:440px; max-width:92vw; padding:32px 28px 24px; text-align:center; box-shadow:0 30px 80px rgba(16,20,45,.4); animation:dcPop .3s cubic-bezier(.34,1.56,.64,1); }
    .dc-icon { position:relative; width:64px; height:64px; margin:0 auto 18px; border-radius:50%; background:linear-gradient(135deg,#ff7a72,#f6473c); color:#fff; display:flex; align-items:center; justify-content:center; font-size:27px; box-shadow:0 10px 26px rgba(246,71,60,.42); }
    .dc-icon .ring { position:absolute; inset:0; border-radius:50%; border:3px solid rgba(246,71,60,.5); animation:dcRing 1.7s ease-out infinite; }
    .dc-title { font-size:18px; font-weight:800; color:#2f3654; margin:0 0 9px; }
    .dc-msg { font-size:13.5px; color:#64718a; line-height:1.7; margin-bottom:18px; white-space:pre-line; }
    .dc-inwrap { text-align:left; margin-bottom:18px; }
    .dc-inwrap .lb { font-size:12.5px; color:#98a6ad; margin-bottom:7px; }
    .dc-inwrap .lb b { color:#f6473c; font-family:SFMono-Regular,Menlo,Consolas,monospace; }
    .dc-in { width:100%; border:1.5px solid #eef0f5; border-radius:11px; padding:10px 13px; font-family:SFMono-Regular,Menlo,Consolas,monospace; font-size:14px; color:#34395e; transition:border-color .15s,box-shadow .15s; }
    .dc-in:focus { outline:none; border-color:#f6473c; box-shadow:0 0 0 3px rgba(246,71,60,.14); }
    .dc-acts { display:flex; gap:11px; }
    .dc-btn { flex:1; border:none; border-radius:11px; padding:12px; font-weight:600; font-size:14px; cursor:pointer; transition:transform .15s,box-shadow .15s,background .15s,opacity .15s; }
    .dc-btn.cancel { background:#f1f3f8; color:#64718a; }
    .dc-btn.cancel:hover { background:#e7eaf1; }
    .dc-btn.ok { background:linear-gradient(135deg,#fc6a63,#e0362d); color:#fff; box-shadow:0 7px 18px rgba(224,54,45,.34); }
    .dc-btn.ok:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 11px 24px rgba(224,54,45,.42); }
    .dc-btn.ok:disabled { opacity:.4; cursor:not-allowed; box-shadow:none; }
    @keyframes dcFade { from{opacity:0} to{opacity:1} }
    @keyframes dcPop { from{opacity:0; transform:translateY(16px) scale(.93)} to{opacity:1; transform:none} }
    @keyframes dcRing { 0%{transform:scale(1);opacity:.55} 100%{transform:scale(1.55);opacity:0} }
    @media (prefers-reduced-motion:reduce){ .dc-overlay,.dc-card{animation:none} .dc-icon .ring{display:none} }
</style>
<div id="dcOverlay" class="dc-overlay" style="display:none">
    <div class="dc-card">
        <div class="dc-icon"><span class="ring"></span><i class="fas fa-exclamation-triangle"></i></div>
        <h5 id="dcTitle" class="dc-title">危险操作确认</h5>
        <div id="dcMsg" class="dc-msg"></div>
        <div id="dcInputWrap" class="dc-inwrap" style="display:none">
            <div class="lb">请输入 <b id="dcWord"></b> 以确认</div>
            <input id="dcInput" class="dc-in" autocomplete="off">
        </div>
        <div class="dc-acts">
            <button type="button" id="dcCancel" class="dc-btn cancel">取消</button>
            <button type="button" id="dcOk" class="dc-btn ok">确认执行</button>
        </div>
    </div>
</div>
<script>
// Turbo 友好：监听只绑一次（document 不随导航替换），元素每次现查（body 会被 Turbo 替换）
(function () {
    if (window.__dcBound) return;
    window.__dcBound = true;
    var pending = null;
    var el = function (id) { return document.getElementById(id); };

    function close() {
        var o = el('dcOverlay'); if (o) o.style.display = 'none';
        var i = el('dcInput'); if (i) i.value = '';
        pending = null;
    }
    function open(form) {
        var overlay = el('dcOverlay'); if (!overlay) return;
        pending = form;
        el('dcMsg').textContent = form.getAttribute('data-dgr') || '确认执行该操作？';
        el('dcTitle').textContent = form.getAttribute('data-dgr-title') || '危险操作确认';
        var word = form.getAttribute('data-dgr-word'), ok = el('dcOk'), input = el('dcInput'), wrap = el('dcInputWrap');
        if (word) {
            wrap.style.display = 'block'; el('dcWord').textContent = word;
            ok.disabled = true; setTimeout(function () { input.focus(); }, 50);
            input.oninput = function () { ok.disabled = input.value.trim() !== word; };
        } else {
            wrap.style.display = 'none'; ok.disabled = false; input.oninput = null;
        }
        overlay.style.display = 'flex';
    }
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.matches && f.matches('form[data-dgr]') && !f.__dcPassed) {
            e.preventDefault(); e.stopPropagation(); open(f);
        }
    }, true);
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('#dcOk,#dcCancel') : null;
        if (t && t.id === 'dcOk') {
            if (t.disabled || !pending) return;
            var f = pending; f.__dcPassed = true; close();
            if (f.requestSubmit) { f.requestSubmit(); } else { f.submit(); }   // requestSubmit 触发事件，让 Turbo 接管
        } else if (t && t.id === 'dcCancel') {
            close();
        } else if (e.target.id === 'dcOverlay') {
            close();
        }
    });
})();
</script>
</body>
</html>
