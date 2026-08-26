@php
    $crispWebsiteId = setting('crisp_website_id', '');
    $supportWidget = setting('support_widget', '');
    $supportTg = setting('support_tg', '');
    $supportGroup = setting('support_group', '');
    $supportHours = setting('support_hours', '');
    $csUser = auth()->user();
@endphp

@if($crispWebsiteId !== '')
    {{-- Crisp 在线客服 + 身份绑定（与真站网页端一致） --}}
    <script>
        // Turbo 每次导航都会重跑本段;已注入过就跳过,避免重复加载 Crisp 脚本
        if (!window.__crispLoaded) {
            window.__crispLoaded = true;
            window.$crisp = [];
            window.CRISP_WEBSITE_ID = {{ Illuminate\Support\Js::from($crispWebsiteId) }};
            (function () {
                var d = document, s = d.createElement('script');
                s.src = 'https://client.crisp.chat/l.js'; s.async = 1;
                d.getElementsByTagName('head')[0].appendChild(s);
            })();
            window.$crisp.push(['safe', true]);
            @if($csUser && setting('crisp_bind_identity', '0') === '1')
            window.$crisp.push(['set', 'user:email', [{{ Illuminate\Support\Js::from($csUser->email) }}]]);
            window.$crisp.push(['set', 'user:nickname', [{{ Illuminate\Support\Js::from($csUser->name ?: $csUser->email) }}]]);
            @endif
        }
    </script>
@elseif($supportWidget !== '')
    {{-- 其它第三方真人客服（Tawk.to / 美洽 等）：原样注入 --}}
    {!! $supportWidget !!}
@else
    {{-- 自建悬浮客服面板 --}}
    <div id="cs-widget">
        <div id="cs-panel">
            <div class="cs-head">
                <div><div class="cs-title">在线客服</div><div class="cs-sub">{{ $supportHours ?: '有问题随时联系我们' }}</div></div>
                <button type="button" class="cs-x" onclick="csToggle(false)" aria-label="关闭">&times;</button>
            </div>
            <div class="cs-body">
                @if($supportTg)
                <a class="cs-item" href="{{ $supportTg }}" target="_blank" rel="noopener">
                    <span class="cs-ic" style="background:#eaf4ff;color:#2f9fe0"><i class="fab fa-telegram-plane"></i></span>
                    <span class="cs-txt"><b>Telegram 客服</b><small>点击直达客服，通常几分钟内回复</small></span>
                    <i class="fas fa-chevron-right cs-arr"></i>
                </a>
                @endif
                @if($supportGroup)
                <a class="cs-item" href="{{ $supportGroup }}" target="_blank" rel="noopener">
                    <span class="cs-ic" style="background:#eafbf0;color:#3fae57"><i class="fas fa-users"></i></span>
                    <span class="cs-txt"><b>用户交流群</b><small>加入群组，获取公告与互助</small></span>
                    <i class="fas fa-chevron-right cs-arr"></i>
                </a>
                @endif
                <a class="cs-item" href="/user/ticket">
                    <span class="cs-ic" style="background:#f0edff;color:#6777ef"><i class="fas fa-ticket-alt"></i></span>
                    <span class="cs-txt"><b>提交工单</b><small>复杂问题走工单，留档可追溯</small></span>
                    <i class="fas fa-chevron-right cs-arr"></i>
                </a>
            </div>
            @if(! $supportTg && ! $supportGroup)
            <div class="cs-foot">管理员尚未配置即时客服，请通过工单联系。</div>
            @endif
        </div>
        <button type="button" id="cs-bubble" onclick="csToggle()" aria-label="联系客服">
            <span class="cs-ring"></span><span class="cs-ring cs-ring2"></span>
            <span class="cs-dot"></span>
            <i class="fas fa-comment-dots cs-i cs-i-open"></i>
            <i class="fas fa-chevron-down cs-i cs-i-close"></i>
            <span class="cs-label">在线客服</span>
        </button>
    </div>

    <style>
        #cs-widget { position: fixed; right: 22px; bottom: 22px; z-index: 1080; }
        #cs-bubble { position: relative; width: 58px; height: 58px; border-radius: 50%; border: none;
            cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0;
            background: linear-gradient(140deg, #7b8bff 0%, #6777ef 50%, #5a49d6 100%); color: #fff;
            box-shadow: 0 10px 24px rgba(103,119,239,.42), inset 0 1px 1px rgba(255,255,255,.35);
            transition: transform .2s cubic-bezier(.34,1.56,.64,1), box-shadow .2s; }
        #cs-bubble:hover { transform: translateY(-3px); box-shadow: 0 16px 30px rgba(103,119,239,.5), inset 0 1px 1px rgba(255,255,255,.35); }
        #cs-bubble:active { transform: translateY(-1px) scale(.97); }
        /* 图标切换 */
        .cs-i { position: absolute; transition: opacity .18s, transform .22s; }
        .cs-i-open { font-size: 23px; opacity: 1; transform: rotate(0) scale(1); }
        .cs-i-close { font-size: 20px; opacity: 0; transform: rotate(-90deg) scale(.6); }
        #cs-widget.open .cs-i-open { opacity: 0; transform: rotate(90deg) scale(.6); }
        #cs-widget.open .cs-i-close { opacity: 1; transform: rotate(0) scale(1); }
        /* 在线绿点 */
        .cs-dot { position: absolute; top: 3px; right: 3px; width: 13px; height: 13px; border-radius: 50%;
            background: #2ed47a; border: 2.5px solid #fff; box-shadow: 0 0 0 rgba(46,212,122,.6);
            animation: cs-dot 2s ease-out infinite; }
        #cs-widget.open .cs-dot { opacity: 0; }
        @keyframes cs-dot { 0% { box-shadow: 0 0 0 0 rgba(46,212,122,.55); } 70%,100% { box-shadow: 0 0 0 7px rgba(46,212,122,0); } }
        /* 呼吸光环 */
        .cs-ring { position: absolute; inset: 0; border-radius: 50%; background: rgba(103,119,239,.35);
            animation: cs-ring 2.6s ease-out infinite; pointer-events: none; }
        .cs-ring2 { animation-delay: 1.3s; }
        #cs-widget.open .cs-ring { display: none; }
        @keyframes cs-ring { 0% { transform: scale(1); opacity: .5; } 80%,100% { transform: scale(1.7); opacity: 0; } }
        /* 悬停滑出标签 */
        .cs-label { position: absolute; right: 68px; white-space: nowrap; background: #34395e; color: #fff;
            font-size: 13px; font-weight: 600; padding: 7px 13px; border-radius: 9px; opacity: 0;
            transform: translateX(8px); pointer-events: none; transition: opacity .18s, transform .18s;
            box-shadow: 0 6px 16px rgba(45,55,90,.22); }
        .cs-label::after { content: ''; position: absolute; right: -5px; top: 50%; transform: translateY(-50%) rotate(45deg);
            width: 10px; height: 10px; background: #34395e; border-radius: 2px; }
        #cs-bubble:hover .cs-label { opacity: 1; transform: translateX(0); }
        #cs-widget.open .cs-label { display: none; }
        @media (prefers-reduced-motion: reduce) { .cs-ring, .cs-dot { animation: none; } }
        #cs-panel { position: absolute; right: 0; bottom: 66px; width: 320px; max-width: calc(100vw - 44px);
            background: #fff; border-radius: 16px; box-shadow: 0 16px 44px rgba(45,55,90,.22);
            overflow: hidden; transform-origin: bottom right; opacity: 0; transform: translateY(10px) scale(.96);
            pointer-events: none; transition: opacity .18s, transform .18s; }
        #cs-widget.open #cs-panel { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
        .cs-head { display: flex; align-items: center; justify-content: space-between;
            padding: 16px 18px; color: #fff; background: linear-gradient(135deg, #6777ef, #4d5ed0); }
        .cs-title { font-size: 15px; font-weight: 700; }
        .cs-sub { font-size: 12px; opacity: .9; margin-top: 2px; }
        .cs-x { background: none; border: none; color: #fff; font-size: 22px; line-height: 1; cursor: pointer; opacity: .85; }
        .cs-x:hover { opacity: 1; }
        .cs-body { padding: 8px; }
        .cs-item { display: flex; align-items: center; gap: 12px; padding: 11px 12px; border-radius: 11px;
            text-decoration: none; transition: background .15s; }
        .cs-item:hover { background: #f6f7fb; text-decoration: none; }
        .cs-ic { flex: 0 0 40px; width: 40px; height: 40px; border-radius: 11px; display: flex;
            align-items: center; justify-content: center; font-size: 17px; }
        .cs-txt { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.35; }
        .cs-txt b { color: #34395e; font-size: 14px; font-weight: 600; }
        .cs-txt small { color: #98a6ad; font-size: 12px; }
        .cs-arr { color: #cfd4e0; font-size: 12px; }
        .cs-foot { padding: 4px 18px 16px; color: #98a6ad; font-size: 12px; }
    </style>
    <script>
        window.csToggle = function (force) {
            var w = document.getElementById('cs-widget');
            if (!w) return;
            var open = typeof force === 'boolean' ? force : !w.classList.contains('open');
            w.classList.toggle('open', open);
        };
        // 一次性绑定:Turbo 每次导航都会重跑本段,无守卫会累积 document 监听器
        if (!window.__csBound) {
            window.__csBound = true;
            document.addEventListener('click', function (e) {
                var w = document.getElementById('cs-widget');
                if (w && w.classList.contains('open') && !w.contains(e.target)) { w.classList.remove('open'); }
            });
        }
    </script>
@endif
