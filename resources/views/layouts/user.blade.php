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
    <style>body{background-color:#f4f6f9}</style>
    @yield('head')
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
            <ul class="navbar-nav navbar-right" style="display:flex;align-items:center;gap:10px">
                @php $unread = auth()->user()->unreadNotificationCount(); $recentNotis = auth()->user()->notifications()->limit(5)->get(); @endphp
                <li class="nav-item" style="position:relative">
                    <a href="/user/messages" id="notiBell" class="nav-link nav-link-lg" style="position:relative;color:#fff" title="我的消息">
                        <i class="fas fa-bell"></i>
                        @if($unread > 0)<span style="position:absolute;top:2px;right:0;background:#fc544b;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;line-height:16px;text-align:center;border-radius:9px;padding:0 4px">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
                    </a>
                    <div id="notiPanel" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:340px;max-width:90vw;background:#fff;border-radius:14px;box-shadow:0 16px 44px rgba(30,40,80,.22);overflow:hidden;z-index:1090">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-bottom:1px solid #f1f3f8">
                            <span style="font-weight:700;color:#34395e;font-size:14px">消息 @if($unread > 0)<span style="color:#fc544b;font-size:12px">· {{ $unread }} 未读</span>@endif</span>
                            @if($unread > 0)<form method="POST" action="/user/messages/read-all" style="margin:0">@csrf<button style="border:none;background:none;color:#6777ef;font-size:12px;font-weight:600;cursor:pointer;padding:0">全部已读</button></form>@endif
                        </div>
                        <div style="max-height:360px;overflow-y:auto">
                            @php $tColor = ['system' => '#6777ef', 'expiry' => '#e6912a', 'marketing' => '#7c4ddb', 'notice' => '#3aa0c7']; @endphp
                            @forelse($recentNotis as $n)
                            <a href="/user/messages" style="display:block;padding:12px 16px;border-bottom:1px solid #f6f7fb;text-decoration:none;background:{{ $n->read_at ? '#fff' : '#f7f9ff' }}">
                                <div style="display:flex;align-items:center;gap:7px;margin-bottom:3px">
                                    @if(! $n->read_at)<span style="width:7px;height:7px;border-radius:50%;background:#fc544b;flex:0 0 7px"></span>@endif
                                    <span style="width:6px;height:6px;border-radius:50%;background:{{ $tColor[$n->type] ?? '#98a6ad' }};flex:0 0 6px"></span>
                                    <b style="font-size:13px;color:#34395e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1">{{ $n->title }}</b>
                                    <span style="font-size:11px;color:#b5bdc9;flex:0 0 auto">{{ $n->created_at?->diffForHumans(null, true) }}</span>
                                </div>
                                <div style="font-size:12px;color:#8a95a6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-left:14px">{{ \Illuminate\Support\Str::limit($n->content, 42) }}</div>
                            </a>
                            @empty
                            <div style="padding:34px 16px;text-align:center;color:#b5bdc9;font-size:13px"><i class="fas fa-bell-slash fa-2x mb-2 d-block" style="opacity:.4"></i>暂无消息</div>
                            @endforelse
                        </div>
                        <a href="/user/messages" style="display:block;padding:11px;text-align:center;font-size:13px;color:#6777ef;font-weight:600;text-decoration:none;background:#fafbff">查看全部消息 →</a>
                    </div>
                </li>
                <li class="nav-item"><span class="d-none d-lg-inline" style="color:#fff">Hi, {{ auth()->user()->name }}</span></li>
                <li class="nav-item">
                    <form method="POST" action="/logout">@csrf
                        <button class="btn btn-sm" style="background:#fff;color:#6777ef;border:none;font-weight:600"><i class="fas fa-sign-out-alt"></i> 退出登录</button>
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
                    <li class="{{ request()->is('user/subscribe-log') ? 'active' : '' }}"><a class="nav-link" href="/user/subscribe-log"><i class="fas fa-history"></i><span>订阅记录</span></a></li>
                    <li class="{{ request()->is('user/downloads') ? 'active' : '' }}"><a class="nav-link" href="/user/downloads"><i class="fas fa-download"></i><span>下载和教程</span></a></li>
                    <li class="{{ request()->is('user/ticket*') ? 'active' : '' }}"><a class="nav-link" href="/user/ticket"><i class="far fa-comments"></i><span>工单支持</span></a></li>
                </ul>
            </aside>
        </div>

        <div class="main-content">
            <section class="section">
                <div class="section-header" style="display:flex;align-items:center;flex-wrap:wrap;gap:8px">
                    <h1 style="margin:0">@yield('title', '用户中心')</h1>
                    @hasSection('header-action')<div style="margin-left:auto">@yield('header-action')</div>@endif
                </div>
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
<div id="copy-toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#34395e;color:#fff;padding:12px 22px;border-radius:8px;font-size:14px;box-shadow:0 4px 16px rgba(0,0,0,.2);opacity:0;pointer-events:none;transition:opacity .2s;z-index:9999">已复制订阅链接</div>
<script>
window.copySub = function (text) {
    const done = () => {
        const t = document.getElementById('copy-toast');
        t.style.opacity = '1';
        clearTimeout(window.__copyTimer);
        window.__copyTimer = setTimeout(() => { t.style.opacity = '0'; }, 1800);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done).catch(() => fallback(text, done));
    } else { fallback(text, done); }
    function fallback(t, cb) {
        const ta = document.createElement('textarea');
        ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta); cb();
    }
};
</script>
@php $popup = auth()->user()->popupNotification(); @endphp
@if($popup)
@php $ptMeta = ['system' => ['系统通知', '#6777ef'], 'expiry' => ['到期提醒', '#e6912a'], 'marketing' => ['活动', '#7c4ddb'], 'notice' => ['通知', '#3aa0c7']]; [$ptName, $ptColor] = $ptMeta[$popup->type] ?? ['通知', '#6777ef']; @endphp
<div id="npOverlay" style="position:fixed;inset:0;background:rgba(16,20,35,.5);backdrop-filter:blur(4px);z-index:1300;display:flex;align-items:center;justify-content:center;animation:npFade .2s ease">
    <div style="background:#fff;border-radius:18px;width:420px;max-width:92vw;box-shadow:0 26px 70px rgba(16,20,45,.4);overflow:hidden;animation:npPop .3s cubic-bezier(.34,1.56,.64,1)">
        <div style="padding:20px 22px 0;text-align:center">
            <span style="width:56px;height:56px;border-radius:50%;background:{{ $ptColor }};color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:12px"><i class="fas fa-{{ $popup->type === 'expiry' ? 'clock' : 'bullhorn' }}"></i></span>
            <div style="font-size:11px;font-weight:700;color:{{ $ptColor }};margin-bottom:4px">{{ $ptName }}</div>
            <h5 style="font-weight:800;color:#34395e;margin:0 0 12px">{{ $popup->title }}</h5>
        </div>
        <div style="padding:0 24px;font-size:13.5px;color:#54667a;line-height:1.75;white-space:pre-line;text-align:center;max-height:40vh;overflow-y:auto">{{ $popup->content }}</div>
        <div style="padding:20px 24px 22px">
            <form method="POST" action="/user/messages/{{ $popup->id }}/read">@csrf
                @if($popup->type === 'expiry')
                <a href="/user/shop" class="btn btn-block" style="border-radius:11px;background:linear-gradient(135deg,#6777ef,#5a67e8);color:#fff;font-weight:600;margin-bottom:8px">去续费</a>
                @endif
                <button class="btn btn-block" style="border-radius:11px;background:#f1f3f8;color:#64718a;font-weight:600;border:none">我知道了</button>
            </form>
        </div>
    </div>
</div>
<style>@keyframes npFade{from{opacity:0}to{opacity:1}}@keyframes npPop{from{opacity:0;transform:translateY(16px) scale(.93)}to{opacity:1;transform:none}}@media(prefers-reduced-motion:reduce){#npOverlay,#npOverlay>div{animation:none}}</style>
@endif
@include('partials.support')
<script>
// 铃铛下拉:点铃铛切换,点外部关闭(事件委托,Turbo 友好,元素现查)
(function () {
    if (window.__notiBound) return;
    window.__notiBound = true;
    document.addEventListener('click', function (e) {
        var panel = document.getElementById('notiPanel');
        if (!panel) return;
        if (e.target.closest('#notiBell')) {
            e.preventDefault();
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
        } else if (!e.target.closest('#notiPanel')) {
            panel.style.display = 'none';
        }
    });
})();
</script>
</body>
</html>
