@extends('layouts.admin')
@section('title', $node->exists ? '编辑节点' : '添加节点')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-server text-primary"></i> {{ $node->exists ? '编辑节点' : '添加节点' }}</h4>
    <a href="/admin/nodes" class="btn btn-light" style="border-radius:9px">返回</a>
</div>

<form method="POST" action="{{ $node->exists ? '/admin/nodes/'.$node->id : '/admin/nodes' }}" class="adm-form">@csrf @if($node->exists)@method('PUT')@endif
    <div class="card adm-form-card">
        <div class="card-header"><span class="ic"><i class="fas fa-sliders-h"></i></span><h4>基本信息</h4></div>
        <div class="card-body">
            <div class="row">
            {{-- `[!!]` 预设:新建节点时最难的不是填哪个框,是【知道哪几个框要一起动】。
                 协议/传输/TLS/flow/REALITY 是一组互相约束的选择 —— 选错组合不会
                 当场报错,而是装完之后客户端连不上。这里把两种已验证的组合做成按钮。 --}}
            @if(! $node->exists)
            <div class="alert alert-light border mb-3" style="background:#f8f9fc">
                <strong style="color:#34395e"><i class="fas fa-magic text-primary mr-1"></i>先选一种，再改细节</strong>
                <div class="mt-2">
                    <button type="button" class="btn btn-outline-primary btn-sm mr-2 js-preset"
                            data-p='{"type":"vmess","net":"tcp","tls":"0","flow":"","reality_enabled":"0"}'>
                        简单节点<small class="d-block text-muted">VMess + TCP · 先跑通用这个</small>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm mr-2 js-preset"
                            data-p='{"type":"vless","net":"tcp","tls":"1","flow":"xtls-rprx-vision","reality_enabled":"1"}'>
                        抗封锁节点<small class="d-block text-muted">VLESS + REALITY + vision · 还需填 dest</small>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm js-preset"
                            data-p='{"port":"0","role":"relay"}'>
                        中转节点<small class="d-block text-muted">端口 0 · 监听来自转发规则</small>
                    </button>
                </div>
            </div>
            @endif

                <div class="form-group col-md-6"><label>节点名称</label><input name="name" value="{{ old('name', $node->name) }}" class="form-control" placeholder="如：香港01" required></div>
                <div class="form-group col-md-6"><label>连接地址（中转入口域名/IP）</label><input name="server" value="{{ old('server', $node->server) }}" class="form-control" required></div>
                <div class="form-group col-md-3"><label>端口</label><input name="port" type="number" value="{{ old('port', $node->port) }}" class="form-control" required></div>
                <div class="form-group col-md-3"><label>协议</label><select name="type" class="form-control"><option value="vmess" @selected(old('type', $node->type ?? 'vmess') == 'vmess')>VMess</option><option value="vless" @selected(old('type', $node->type) == 'vless')>VLESS</option></select></div>
                <div class="form-group col-md-3"><label>传输</label><select name="net" class="form-control" id="netSel"><option value="tcp" @selected(old('net', $node->net) == 'tcp')>TCP</option><option value="ws" @selected(old('net', $node->net) == 'ws')>WebSocket</option></select></div>
                <div class="form-group col-md-3"><label>TLS</label><select name="tls" class="form-control"><option value="0" @selected(! old('tls', $node->tls))>关闭</option><option value="1" @selected(old('tls', $node->tls))>开启</option></select></div>
            </div>
            <div class="row" id="wsRow">
                <div class="form-group col-md-6"><label>WS 路径（net=ws 时）</label><input name="path" value="{{ old('path', $node->path) }}" class="form-control" placeholder="/"></div>
                <div class="form-group col-md-6"><label>Host / SNI（ws Host 或 TLS SNI，选填）</label><input name="host" value="{{ old('host', $node->host) }}" class="form-control"></div>
            </div>

            {{-- VLESS 现代抗封:flow(vision) + REALITY + PROXY 头。仅 VLESS 有意义;VMess 忽略。 --}}
            <div class="row">
                <div class="form-group col-md-3"><label>Flow（VLESS）</label><select name="flow" class="form-control"><option value="" @selected(! old('flow', $node->flow))>无</option><option value="xtls-rprx-vision" @selected(old('flow', $node->flow) == 'xtls-rprx-vision')>xtls-rprx-vision</option></select></div>
                <div class="form-group col-md-3"><label>REALITY</label><select name="reality_enabled" class="form-control"><option value="0" @selected(! old('reality_enabled', $node->usesReality()))>关闭</option><option value="1" @selected(old('reality_enabled', $node->usesReality()))>启用</option></select><small class="text-muted">仅 VLESS;启用后填 dest,密钥自动生成</small></div>
                <div class="form-group col-md-6"><label>REALITY dest（借用真站）</label>
                    <div class="input-group">
                        <input name="reality_dest" id="destInput" value="{{ old('reality_dest', $node->reality_dest) }}" class="form-control" placeholder="www.apple.com:443">
                        {{-- `[!]` 筛查结果就在同一页上，还要人肉抄一遍域名是多余的一步，
                             而手抄正是打错字的地方。挑"合格且最快"的那个填进去。 --}}
                        @php
                            $best = collect($node->dest_scan_result['results'] ?? [])
                                ->where('verdict', 'pass')->sortBy('latency_ms')->first();
                        @endphp
                        @if($best)
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-success" id="useBest"
                                    data-host="{{ $best['host'] }}" title="用筛查结果里合格且最快的那个">
                                用最优（{{ $best['host'] }} {{ $best['latency_ms'] }}ms）
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-6"><label>REALITY server_names（SNI，逗号/换行分隔）</label><textarea name="reality_server_names" rows="2" class="form-control" placeholder="www.apple.com">{{ old('reality_server_names', is_array($node->reality_server_names) ? implode(', ', $node->reality_server_names) : '') }}</textarea></div>
                <div class="form-group col-md-3"><label>重新生成密钥</label><select name="reality_regen" class="form-control"><option value="0">否（保留现有）</option><option value="1">是（换新密钥对）</option></select><small class="text-danger">换新后旧订阅立即失效,客户端报 x509 证书错(非证书问题),须公告全员刷新订阅</small></div>
                <div class="form-group col-md-3"><label>接受 PROXY 头</label><select name="accept_proxy_protocol" class="form-control"><option value="0" @selected(! old('accept_proxy_protocol', $node->accept_proxy_protocol ?? false))>关闭</option><option value="1" @selected(old('accept_proxy_protocol', $node->accept_proxy_protocol ?? false))>开启（落地在中转后面时）</option></select><small class="text-muted">开了必须防火墙只放行中转 IP</small></div>
            </div>
            {{-- 角色与额度（ADR-008：中转并入后，这两项必须能在后台设置。
                 [!!] 此前表单没有 role，新建的节点一律是 DB 默认的 landing ——
                 也就是【后台根本建不出中转节点】，只能去数据库里改。 --}}
            <div class="row">
                <div class="form-group col-md-4"><label>角色</label>
                    <select name="role" class="form-control">
                        @foreach(['landing' => '落地（认证用户、发订阅）', 'relay' => '中转（只透传，不碰用户名单）', 'springboard' => '跳板', 'front' => '入口', 'both' => '兼作落地与中转'] as $k => $v)
                            <option value="{{ $k }}" @selected(old('role', $node->role ?? 'landing') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">[!] 中转/跳板/入口<strong>拿不到用户名单</strong>（D-1），它们的监听来自转发规则。</small></div>
                <div class="form-group col-md-4"><label>整机额度（GB，0=不限）</label>
                    <input name="quota_gb" type="number" min="0" value="{{ old('quota_gb', $node->quota_gb ?? 0) }}" class="form-control">
                    <small class="text-muted">按机房账单口径的整机网卡用量，不是代理流量。</small></div>
                <div class="form-group col-md-4"><label>额度重置日</label>
                    <input name="quota_reset_day" type="number" min="1" max="28" value="{{ old('quota_reset_day', $node->quota_reset_day ?? 1) }}" class="form-control"></div>
            </div>

            <div class="row">
                <div class="form-group col-md-8"><label>dest 候选清单（换行/逗号分隔，节点上筛查）</label>
                    <textarea name="dest_scan_candidates" rows="3" class="form-control" placeholder="www.a.example&#10;www.b.example">{{ old('dest_scan_candidates', $node->dest_scan_candidates) }}</textarea>
                    <small class="text-muted">保存后由【该节点】去扫（可达与延迟是节点到那个站的关系，面板扫没有意义）。内容不变不会重扫。</small></div>
                <div class="form-group col-md-4"><label>强制重扫（清单没变时）</label>
                    <select name="dest_scan_rerun" class="form-control"><option value="0">否</option><option value="1">是</option></select>
                    <small class="text-muted">节点两轮之间最少间隔 15 分钟。</small></div>
            </div>
            @if($node->exists && $node->dest_scan_result)
            @php $rs = $node->dest_scan_result['results'] ?? []; @endphp
            <div class="row"><div class="form-group col-md-12">
                <label>筛查结果（{{ $node->dest_scan_at?->diffForHumans() }}，scan_id={{ $node->dest_scan_result['scan_id'] ?? '' }}）</label>
                <table class="table table-sm table-bordered mb-1">
                    <thead><tr><th>域名</th><th>判定</th><th>TLS1.3</th><th>X25519</th><th>h2</th><th>密钥组</th><th>延迟</th><th>备注</th></tr></thead>
                    <tbody>
                    @foreach($rs as $r)
                        <tr>
                            <td>{{ $r['host'] ?? '' }}</td>
                            <td>@if(($r['verdict'] ?? '') === 'pass')<span class="badge badge-success">pass</span>
                                @elseif(($r['verdict'] ?? '') === 'error')<span class="badge badge-warning">{{ $r['verdict'] }}</span>
                                @else<span class="badge badge-danger">{{ $r['verdict'] ?? '' }}</span>@endif</td>
                            <td>{{ ($r['tls13'] ?? false) ? '✓' : '✗' }}</td>
                            <td>{{ ($r['x25519'] ?? false) ? '✓' : '✗' }}</td>
                            <td>{{ ($r['h2'] ?? false) ? '✓' : '✗' }}</td>
                            <td>{{ $r['key_group'] ?? '' }}</td>
                            <td>{{ ($r['latency_ms'] ?? 0) ?: '-' }}{{ ($r['latency_ms'] ?? 0) ? 'ms' : '' }}</td>
                            <td class="text-muted">{{ $r['cdn_hint'] ?? ($r['error'] ?? '') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                {{-- `[!]` 四关全过只是及格线。选谁还要看冷门度、以及这个 SNI 出现在
                     【客户端实际连的那一跳】的 IP 上自不自然 —— 有中转时那一跳是中转，
                     工具判不了，所以这里【不排序、不推荐】。 --}}
                <small class="text-muted">✅ 只表示过了四关（TLS1.3 / X25519 可协商 / 非 CDN / h2），
                    是<strong>及格线不是推荐</strong>：还要挑够冷门、且对<strong>客户端实际连的那一跳</strong>（有中转时是中转）自然的。
                    ⚠️ error 表示<strong>节点连不上</strong>，不等于该站不合格。</small>
            </div></div>
            @php $same = \App\Models\Node::where('reality_dest', $node->reality_dest)
                    ->where('id', '!=', $node->id)->where('reality_dest', '!=', '')->pluck('name'); @endphp
            @if($node->reality_dest && $same->isNotEmpty())
            <div class="row"><div class="form-group col-md-12">
                <div class="alert alert-warning mb-0">这个 dest 还被 <strong>{{ $same->count() }}</strong> 个节点用着（{{ $same->take(5)->implode('、') }}）——
                    dest 撞车意味着<strong>一次识别全灭</strong>，建议各节点用不同的。</div>
            </div></div>
            @endif
            @endif
            @if($node->exists && $node->usesReality())
            <div class="row"><div class="form-group col-md-12"><label>REALITY public_key（客户端订阅自动带,只读）</label><input value="{{ $node->reality_public_key }}" class="form-control" readonly><small class="text-muted">short_ids: {{ implode(', ', $node->reality_short_ids ?? []) }} · private_key 仅下发落地 agent,不显示</small></div></div>
            @endif
        </div>
    </div>

    <div class="card adm-form-card">
        <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#63c76a,#3fae57)"><i class="fas fa-tachometer-alt"></i></span><h4>计费 / 权限</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-3"><label>流量倍率</label><input name="traffic_rate" type="number" step="0.1" value="{{ old('traffic_rate', $node->traffic_rate) }}" class="form-control" required></div>
                <div class="form-group col-md-3"><label>等级门槛（class≥可连）</label><input name="node_class" type="number" value="{{ old('node_class', $node->node_class ?? 0) }}" class="form-control" required></div>
                <div class="form-group col-md-3"><label>节点限速 Mbps（0不限）</label><input name="speed_limit" type="number" value="{{ old('speed_limit', $node->speed_limit ?? 0) }}" class="form-control"></div>
                <div class="form-group col-md-3"><label>分组（0不限）</label><input name="node_group" type="number" value="{{ old('node_group', $node->node_group ?? 0) }}" class="form-control"></div>
                <div class="form-group col-md-3"><label>排序</label><input name="sort" type="number" value="{{ old('sort', $node->sort ?? 0) }}" class="form-control"></div>
                <div class="form-group col-md-3"><label>对用户开放</label><select name="enabled" class="form-control"><option value="1" @selected(old('enabled', $node->enabled ?? true))>开放</option><option value="0" @selected(! old('enabled', $node->enabled ?? true))>排空/维护(不服务用户)</option></select><small class="text-muted">排空时 agent 照常在线,但用户从此节点漏干,可安全下线</small></div>
            </div>
        </div>
    </div>

    <button class="btn adm-btn"><i class="fas fa-save"></i> 保存</button>
</form>

@if($node->exists)
<div class="card adm-form-card" style="margin-top:20px">
    <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#7c4ddb,#6636c0)"><i class="fas fa-plug"></i></span><h4>节点对接信息</h4></div>
    <div class="card-body adm-form">
        <p class="form-tip">在节点后端(对接脚本)填入以下信息即可上报流量、拉取用户名单。密钥泄露请重新生成。</p>
        <div class="form-group"><label>节点 ID</label><input class="form-control" value="{{ $node->id }}" readonly style="max-width:200px"></div>
        <div class="form-group"><label>通信密钥（secret）</label>
            <div class="input-group" style="max-width:520px"><input class="form-control" value="{{ $node->secret }}" readonly onclick="this.select()">
                <div class="input-group-append"><form method="POST" action="/admin/nodes/{{ $node->id }}/regenerate-secret" data-dgr="重新生成后旧密钥立即失效，需同步更新节点后端，确认？">@csrf<button class="btn btn-outline-danger" style="border-radius:0 9px 9px 0">重新生成</button></form></div>
            </div>
        </div>
        <div class="form-group"><label>用户名单接口</label><input class="form-control" value="{{ url('/mod_mu/users') }}?node_id={{ $node->id }}&key={{ $node->secret }}" readonly onclick="this.select()"></div>
        <div class="form-group mb-0"><label>流量上报接口</label><input class="form-control" value="{{ url('/mod_mu/users/traffic') }}?node_id={{ $node->id }}&key={{ $node->secret }}" readonly onclick="this.select()"></div>
    </div>
</div>
@endif
<script>
(function () {
    var sel = document.getElementById('netSel'), ws = document.getElementById('wsRow');
    function sync() { if (ws) ws.style.display = (sel && sel.value === 'ws') ? '' : 'none'; }
    if (sel) { sel.addEventListener('change', sync); sync(); }

    // [!!] 页面上第一个 <form> 是导航栏的搜索/登出表单,不是节点表单 ——
    // 直接 querySelector('form') 会拿错,get() 全返回 null,预设/一键 dest 点了没反应。
    // 用节点表单里必有的字段(type)定位到它自己那个 form。
    var typeEl = document.querySelector('[name="type"]');
    var f = typeEl ? typeEl.closest('form') : document.querySelector('form');
    var get = function (n) { return f ? f.querySelector('[name="' + n + '"]') : null; };

    // 预设：一次把一组互相约束的选择填好
    document.querySelectorAll('.js-preset').forEach(function (b) {
        b.addEventListener('click', function () {
            var p = JSON.parse(b.dataset.p);
            Object.keys(p).forEach(function (k) {
                var el = get(k);
                if (el) { el.value = p[k]; el.dispatchEvent(new Event('change')); }
            });
            check();
        });
    });

    // 一键用筛查结果里最优的那个
    var best = document.getElementById('useBest');
    if (best) {
        best.addEventListener('click', function () {
            var h = best.dataset.host;
            get('reality_dest').value = h + ':443';
            var sn = get('reality_server_names');
            if (sn && !sn.value.trim()) { sn.value = h; }   // SNI 通常就是 dest 的域名
            check();
        });
    }

    // `[!!]` 组合校验:协议/传输/TLS/flow/REALITY 互相约束,而选错【不会当场报错】——
    // 要等装完、客户端连不上才知道。在这里当场说出来。
    // 规则来源:compatibility/vision-matrix.md（vision 只在 vless + tcp + tls/reality 上成立）
    var warn = document.createElement('div');
    warn.className = 'alert alert-warning py-2 mt-2';
    warn.style.display = 'none';
    if (f) { f.insertBefore(warn, f.firstChild); }

    function check() {
        var msgs = [];
        var type = get('type'), net = get('net'), flow = get('flow'),
            rea = get('reality_enabled'), tls = get('tls'), dest = get('reality_dest');
        if (!type) { return; }
        var isVless = type.value === 'vless',
            hasFlow = flow && flow.value === 'xtls-rprx-vision',
            hasReality = rea && rea.value === '1';

        if (hasReality && !isVless) { msgs.push('REALITY 只能用在 VLESS 上'); }
        if (hasFlow && !isVless) { msgs.push('vision 流控只能用在 VLESS 上'); }
        if (hasFlow && net && net.value !== 'tcp') { msgs.push('vision 只在 TCP 传输上成立（ws/grpc 都不行）'); }
        if (hasFlow && !hasReality && tls && tls.value !== '1') {
            msgs.push('vision 需要 TLS 或 REALITY —— 两个都没开的话客户端连不上');
        }
        if (hasReality && dest && !dest.value.trim()) { msgs.push('启用了 REALITY 但没填 dest'); }
        if (hasReality && tls && tls.value === '1') {
            msgs.push('REALITY 与 TLS 是两种安全层，同时开会以 REALITY 为准 —— TLS 那项可以关掉');
        }

        warn.style.display = msgs.length ? '' : 'none';
        warn.innerHTML = msgs.length
            ? '<strong>这样配装完会连不上：</strong><ul class="mb-0 mt-1"><li>' + msgs.join('</li><li>') + '</li></ul>'
            : '';
    }

    ['type', 'net', 'tls', 'flow', 'reality_enabled', 'reality_dest'].forEach(function (n) {
        var el = get(n);
        if (el) { el.addEventListener('change', check); el.addEventListener('input', check); }
    });
    check();
})();
</script>
@endsection
