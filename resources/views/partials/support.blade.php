@php
    $supportWidget = setting('support_widget', '');
    $supportTg = setting('support_tg', '');
    $supportGroup = setting('support_group', '');
    $supportHours = setting('support_hours', '');
@endphp

@if($supportWidget !== '')
    {{-- 第三方真人客服（Tawk.to / Crisp 等）：原样注入 --}}
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
                <a class="cs-item" href="/user/tickets">
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
            <i class="fas fa-headset"></i>
        </button>
    </div>

    <style>
        #cs-widget { position: fixed; right: 22px; bottom: 22px; z-index: 1080; }
        #cs-bubble { width: 54px; height: 54px; border-radius: 50%; border: none; cursor: pointer;
            background: linear-gradient(135deg, #6777ef, #4d5ed0); color: #fff; font-size: 22px;
            box-shadow: 0 8px 22px rgba(103,119,239,.45); transition: transform .18s, box-shadow .18s; }
        #cs-bubble:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 12px 26px rgba(103,119,239,.55); }
        #cs-widget.open #cs-bubble { transform: rotate(90deg); }
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
        document.addEventListener('click', function (e) {
            var w = document.getElementById('cs-widget');
            if (w && w.classList.contains('open') && !w.contains(e.target)) { w.classList.remove('open'); }
        });
    </script>
@endif
