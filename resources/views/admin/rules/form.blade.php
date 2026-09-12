@extends('layouts.admin')
@section('title', $rule->exists ? '编辑规则' : '新建规则')
@section('content')
@php
  $outs = $rule->exists ? $rule->outbounds : collect();
  // [!] 先算成普通数组再用 @selected —— 在 Blade 的 @php 块里定义带 [] 的闭包
  // 会让它的语句解析器报 "Unclosed '[' does not match ')'"。
  $selNodes = old('inbound_node_set', $rule->inbound_node_set) ?? [];
  $selNodes = array_map('intval', $selNodes);

  // [!] 这些数组必须定义在 @php 块里，不能写成 @foreach([...multi-line...] as ...)
  // —— Blade 的语句解析器读不动跨行的数组字面量，报
  // "Unclosed '[' does not match ')'"，且错误只在渲染时才出现。
  $inTypes = ['direct' => 'direct（裸转发）', 'vmess' => 'vmess', 'vless' => 'vless',
              'trojan' => 'trojan', 'socks' => 'socks'];
  $transports = ['tcp', 'ws', 'grpc'];
  $securities = ['none', 'tls', 'reality'];
  $balances = ['roundrobin' => 'roundrobin（轮流）',
               'iphash' => 'iphash（同一来源固定走同一个）',
               'leastconn' => 'leastconn（谁空闲给谁）',
               'leastload' => 'leastload（按延迟）',
               'random' => 'random（随机）'];
  $bkBalances = ['fallback' => 'fallback（主池全灭才用）',
                 'roundrobin' => 'roundrobin', 'random' => 'random'];

  // 「添加一行」用的空白模板。在这里渲染成 JSON 字符串 ——
  // 写成 @json(view(...)->render()) 会让 Blade 的解析器在嵌套的 [] 上报
  // "Unclosed '[' does not match ')'"。
  $io = $rule->inbound_opts ?? [];
  $rl = $io['reality'] ?? [];
  $ws = $io['ws'] ?? [];
  $gr = $io['grpc'] ?? [];

  $outTemplate = json_encode(
      view('admin.rules._outbound', ['i' => '__I__', 'o' => null, 'nodes' => $nodes])->render(),
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  );
@endphp

<div class="adm-head">
  <h4>{{ $rule->exists ? '编辑规则' : '新建规则' }}</h4>
  <div class="adm-tools"><a href="/admin/rules" class="btn btn-light">返回列表</a></div>
</div>

@if (! empty($problems))
  @php
    $rejects = collect($problems)->where('level', 'reject');
    $brokens = collect($problems)->where('level', 'broken');
    $skips   = collect($problems)->where('level', 'skip');
    $warns   = collect($problems)->where('level', 'warn');
  @endphp
  <div class="card adm-form-card">
    <div class="card-header">
      <div class="ic" style="background:{{ ($rejects->count() || $brokens->count()) ? 'linear-gradient(135deg,#fc544b,#e53935)' : 'linear-gradient(135deg,#ffa426,#f39c12)' }}">
        <i class="fas fa-{{ ($rejects->count() || $brokens->count()) ? 'times' : 'exclamation' }}"></i>
      </div>
      <h4>检查结果</h4>
    </div>
    <div class="card-body">
      @foreach ($rejects as $x)
        <div class="danger-note mb-2">
          <strong>节点会拒绝整份规则：</strong>{{ $x['text'] }}
        </div>
      @endforeach
      @if ($rejects->count())
        {{-- `[!!]` 后果的【范围】必须说清楚。"这条规则有问题"会被理解成
             "这条不生效"，而实际是那个节点上的【全部】中转一起停 ——
             两者的紧急程度完全不同。 --}}
        <div class="danger-note mb-2">
          <strong>后果不止这一条规则。</strong>
          agent 校验不通过时会拒绝<strong>整份</strong>下发，
          也就是这些节点上的<strong>所有</strong>转发一起停 ——
          而节点会继续用内存里的旧规则跑，日志里只有一行校验失败。
        </div>
      @endif
      {{-- `[!!]` broken 与 reject 的区别要写在脸上：规则会下发、节点照常跑，
           所以运维不会看到任何异常，**只有用户连不上**。这类故障两端都不
           报错，面板是唯一能在出事前发现它的地方。 --}}
      @foreach ($brokens as $x)
        <div class="danger-note mb-2">
          <strong>会下发也会跑，但用户连不上：</strong>{{ $x['text'] }}
        </div>
      @endforeach
      @foreach ($skips as $x)
        <div class="info-note mb-2"><strong>这一条会被跳过：</strong>{{ $x['text'] }}</div>
      @endforeach
      @foreach ($warns as $x)
        <div class="hint mb-2"><i class="fas fa-info-circle"></i> {{ $x['text'] }}</div>
      @endforeach
      <div class="hint mb-0">
        `[!]` 这是面板侧的检查，权威判定在节点。它只覆盖<strong>已知会咬人</strong>的那些条件，
        通过不等于一定没问题；但报出来的都是真会被拒的。
      </div>
    </div>
  </div>
@endif

<form method="post" class="adm-form" action="{{ $rule->exists ? "/admin/rules/{$rule->id}" : '/rules' }}">
  @csrf
  @if ($rule->exists) @method('PUT') @endif

  <div class="card adm-form-card">
    <div class="card-header"><div class="ic"><i class="fas fa-sliders-h"></i></div><h4>基本</h4></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>名称</label>
          <input name="name" class="form-control" required value="{{ old('name', $rule->name) }}">
        </div>
        <div class="form-group col-md-3">
          <label>限速 (Mbps)</label>
          <input name="speed_limit" type="number" min="0" class="form-control"
                 value="{{ old('speed_limit', $rule->speed_limit ?? 0) }}">
          <div class="hint">0 = 不限</div>
        </div>
        <div class="form-group col-md-3 d-flex align-items-center">
          <label class="custom-switch mt-4">
            <input type="checkbox" name="enabled" value="1" class="custom-switch-input"
                   {{ old('enabled', $rule->enabled) ? 'checked' : '' }}>
            <span class="custom-switch-indicator"></span>
            <span class="custom-switch-description">启用</span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <div class="card adm-form-card">
    <div class="card-header"><div class="ic"><i class="fas fa-sign-in-alt"></i></div><h4>入站 —— 本规则在哪些节点上监听什么</h4></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group col-md-4">
          <label>运行节点</label>
          <select name="inbound_node_set[]" class="form-control" multiple size="5" required>
            @foreach ($nodes as $n)
              <option value="{{ $n->id }}" @selected(in_array($n->id, $selNodes, true))>
                {{ $n->label() }}{{ $n->forwards() ? '' : '  ← 角色不是中转' }}
              </option>
            @endforeach
          </select>
          <div class="hint">
            按住 Ctrl 多选。<strong>角色不是中转的节点拿不到规则</strong> ——
            角色在<a href="/admin/nodes">节点与角色</a>里改。
          </div>
        </div>
        <div class="form-group col-md-2">
          <label>协议</label>
          <select name="inbound_type" class="form-control">
            @foreach ($inTypes as $v => $t)
              <option value="{{ $v }}" @selected(old('inbound_type', $rule->inbound_type) === $v)>{{ $t }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group col-md-2">
          <label>监听端口</label>
          <input name="listen_port" class="form-control" required
                 value="{{ old('listen_port', $rule->listen_port) }}" placeholder="10001 或 10100-10110">
        </div>
        <div class="form-group col-md-2">
          <label>传输</label>
          <select name="inbound_transport" class="form-control">
            <option value="">（默认 tcp）</option>
            @foreach ($transports as $v)
              <option value="{{ $v }}" @selected(old('inbound_transport', $rule->inbound_transport) === $v)>{{ $v }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group col-md-2">
          <label>安全层</label>
          <select name="inbound_security" class="form-control">
            <option value="">（默认 none）</option>
            @foreach ($securities as $v)
              <option value="{{ $v }}" @selected(old('inbound_security', $rule->inbound_security) === $v)>{{ $v }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-md-4">
          <label>监听网卡 IP</label>
          <input name="listen_nic_ip" class="form-control"
                 value="{{ old('listen_nic_ip', $rule->listen_nic_ip) }}" placeholder="留空 = 全部网卡">
        </div>
        <div class="form-group col-md-8 d-flex align-items-center">
          <label class="custom-switch mt-4">
            <input type="checkbox" name="accept_proxy_protocol" value="1" class="custom-switch-input"
                   {{ old('accept_proxy_protocol', $rule->accept_proxy_protocol) ? 'checked' : '' }}>
            <span class="custom-switch-indicator"></span>
            <span class="custom-switch-description">接收 PROXY protocol（拿上游传来的真实客户端 IP）</span>
          </label>
        </div>
      </div>
      <div class="alert alert-warning mb-0">
        <strong>开了「接收 PROXY protocol」之后，直连的客户端就连不上了。</strong>
        这是该协议本身的性质（监听端要么期待那个头、要么不期待），不是我们的取舍。
        所以开了它的节点必须<strong>专用于接中转流量</strong>，不能同时挂在用户订阅里。
      </div>
      @if ($rule->exists)
        <hr>
        <div class="d-flex align-items-center">
          <div class="flex-grow-1">
            <label class="mb-1">节点间凭据</label>
            <div class="hint">
              {{ data_get($rule->inbound_cred, 'uuid') ? 'UUID: ' . data_get($rule->inbound_cred, 'uuid') : '尚未生成' }}
            </div>
            <div class="hint">这是<strong>节点之间</strong>的凭据，不是用户凭据 —— 中转不认证用户。</div>
          </div>
          <button formaction="/admin/rules/{{ $rule->id }}/regenerate-cred" formmethod="post"
                  class="btn btn-outline-secondary"
                  onclick="return confirm('重新生成后，引用本规则的上游需要等一个拉取周期才能跟上，期间可能短暂连不上。继续？')">
            重新生成
          </button>
        </div>
      @endif
    </div>
  </div>

  {{-- 入站的传输/安全层参数。按当前形态显示 —— 只有选了 reality 才谈得上
       dest 和密钥，塞一堆用不上的输入框只会让人填错。 --}}
  <div class="card adm-form-card" id="inOptsCard">
    <div class="card-header"><div class="ic"><i class="fas fa-shield-alt"></i></div>
      <h4>入站高级参数</h4></div>
    <div class="card-body">
      <input type="hidden" name="_keep_inbound_opts" value="{{ json_encode($io) }}">

      <div class="in-opt" data-when="transport:ws">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>ws path</label>
            <input name="in_ws_path" class="form-control" value="{{ $ws['path'] ?? '' }}" placeholder="/">
          </div>
          <div class="form-group col-md-6">
            <label>ws host</label>
            <input name="in_ws_host" class="form-control" value="{{ $ws['host'] ?? '' }}">
          </div>
        </div>
      </div>

      <div class="in-opt" data-when="transport:grpc">
        <div class="form-group">
          <label>grpc serviceName</label>
          <input name="in_grpc_service" class="form-control" value="{{ $gr['service_name'] ?? '' }}">
          <div class="hint">grpc 传输没有 serviceName 建不了链</div>
        </div>
      </div>

      <div class="in-opt" data-when="security:reality">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>dest（伪装成谁）</label>
            <input name="in_reality_dest" class="form-control"
                   value="{{ $rl['dest'] ?? '' }}" placeholder="www.apple.com:443">
            <div class="hint">
              <strong>握手时这个地址必须真的连得上</strong> —— 连不上就握手失败。
              挑一个本机到它延迟低、且用 TLS1.3 的大站。
            </div>
          </div>
          <div class="form-group col-md-6">
            <label>server_names</label>
            <input name="in_reality_names" class="form-control"
                   value="{{ implode(' ', $rl['server_names'] ?? []) }}" placeholder="www.apple.com">
            <div class="hint">空格或逗号分隔。出站那侧的 SNI 必须是其中之一</div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>short_ids</label>
            <input name="in_reality_short_ids" class="form-control"
                   value="{{ implode(' ', $rl['short_ids'] ?? []) }}" placeholder="0123abcd">
            <div class="hint">偶数长度的十六进制，最长 16 位</div>
          </div>
        </div>
        @if ($rule->exists)
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>私钥（本节点用）</label>
              <input class="form-control" readonly onclick="this.select()"
                     value="{{ $rl['private_key'] ?? '' }}" placeholder="尚未生成">
            </div>
            <div class="form-group col-md-6">
              <label>公钥（填到拨向本规则的出站上）</label>
              <input class="form-control" readonly onclick="this.select()"
                     value="{{ $rl['public_key'] ?? '' }}" placeholder="尚未生成">
            </div>
          </div>
          <button formaction="/admin/rules/{{ $rule->id }}/reality-keypair" formmethod="post"
                  class="btn btn-outline-secondary"
                  onclick="return confirm('重新生成后，所有拨向本规则的出站都要换成新公钥，否则连不上。继续？')">
            {{ ($rl['private_key'] ?? '') ? '重新生成密钥对' : '生成密钥对' }}
          </button>
          <div class="hint mt-2">
            密钥不接受手输：贴错一个字符是<strong>静默失败</strong> ——
            节点照常起、端口照常通、伪装也像，只有客户端连不上，日志里什么都没有。
          </div>
        @else
          <div class="info-note">保存之后在这里生成密钥对。</div>
        @endif
      </div>

      <div class="hint mb-0" id="inOptsNone">
        当前入站形态（传输 tcp、安全层 none）没有额外参数。
        改上面的「传输」或「安全层」后，对应的参数会出现在这里。
      </div>
    </div>
  </div>

  <div class="card adm-form-card">
    <div class="card-header">
      <div class="ic"><i class="fas fa-sign-out-alt"></i></div><h4>出站 —— 流量发到哪</h4>
      <div class="card-header-action ml-auto"><button type="button" class="btn btn-sm adm-btn" id="addOut">添加一行</button></div>
    </div>
    <div class="card-body">
      <div id="outs">
        @foreach ($outs as $i => $o)
          @include('admin.rules._outbound', ['i' => $i, 'o' => $o, 'nodes' => $nodes])
        @endforeach
      </div>
      @if ($outs->isEmpty())
        <p class="hint mb-0">还没有出站。点「添加一行」。</p>
      @endif
    </div>
  </div>

  <div class="card adm-form-card">
    <div class="card-header"><div class="ic"><i class="fas fa-heartbeat"></i></div><h4>均衡与健康检查</h4></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group col-md-3">
          <label>均衡策略</label>
          <select name="balance" class="form-control">
            @foreach ($balances as $v => $t)
              <option value="{{ $v }}" @selected(old('balance', $rule->balance) === $v)>{{ $t }}</option>
            @endforeach
          </select>
          <div class="hint">用户反映"老是掉登录"时，改成 iphash。</div>
        </div>
        <div class="form-group col-md-3">
          <label>备池策略</label>
          <select name="backup_balance" class="form-control">
            @foreach ($bkBalances as $v => $t)
              <option value="{{ $v }}" @selected(old('backup_balance', $rule->backup_balance) === $v)>{{ $t }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group col-md-2">
          <label>检查间隔 (秒)</label>
          <input name="hc_interval_sec" type="number" min="3" max="3600" class="form-control"
                 value="{{ old('hc_interval_sec', $rule->hc_interval_sec ?? 10) }}">
        </div>
        <div class="form-group col-md-2">
          <label>连续失败几次判死</label>
          <input name="hc_max_fail" type="number" min="1" class="form-control"
                 value="{{ old('hc_max_fail', $rule->hc_max_fail ?? 3) }}">
        </div>
        <div class="form-group col-md-2">
          <label>连续成功几次恢复</label>
          <input name="hc_max_success" type="number" min="1" class="form-control"
                 value="{{ old('hc_max_success', $rule->hc_max_success ?? 2) }}">
        </div>
      </div>
      <label class="custom-switch">
        <input type="checkbox" name="hc_enabled" value="1" class="custom-switch-input"
               {{ old('hc_enabled', $rule->hc_enabled ?? true) ? 'checked' : '' }}>
        <span class="custom-switch-indicator"></span>
        <span class="custom-switch-description">启用主动健康检查</span>
      </label>
      <div class="hint">
        关掉之后，<strong>死掉的上游仍会被轮到</strong>（节点不知道它死了）。
        多出站时强烈建议开着。失败阈值小于成功阈值是刻意的 —— 快摘除、慢恢复，
        免得一条抖动的线路被反复拉回轮转里。
      </div>
    </div>
  </div>

  <div class="mb-5">
    <button class="btn adm-btn px-4">保存</button>
    <a href="/admin/rules" class="btn btn-light">取消</a>
  </div>
</form>

@push('scripts')
<script>
// 新增一行出站：从模板克隆，重编索引。
const TPL = {!! $outTemplate !!};
let idx = {{ $outs->count() }};
document.getElementById('addOut').addEventListener('click', () => {
  document.getElementById('outs').insertAdjacentHTML('beforeend', TPL.replaceAll('__I__', idx++));
});
document.addEventListener('click', e => {
  if (e.target.matches('.rm-out')) e.target.closest('.out-row').remove();
});

// 按当前的传输/安全层显示对应的参数块。
//
// [!] 只是显示与否，【不清空值】—— 从 reality 切走再切回来，填过的
// dest / server_names 还在。真正决定下发内容的是服务端：控制器只把
// 当前形态用得上的块写进库，多余的块不会跟着下发。
// `[!]` 第三个维度 type：出站的凭据字段按协议类型显隐（direct 一个都不要）。
// 入站那边没有 typeSel，传 null 即可。
function syncOpts(scope, transportSel, securitySel, boxSel, typeSel) {
  const t = scope.querySelector(transportSel), s = scope.querySelector(securitySel);
  const y = typeSel ? scope.querySelector(typeSel) : null;
  const tv = t ? t.value || 'tcp' : 'tcp', sv = s ? s.value || 'none' : 'none';
  const yv = y ? y.value || 'direct' : '';
  let shown = 0;
  scope.querySelectorAll(boxSel).forEach(box => {
    const [kind, want] = box.dataset.when.split(':');
    const cur = kind === 'transport' ? tv : (kind === 'security' ? sv : yv);
    const on = cur === want;
    box.style.display = on ? '' : 'none';
    if (on && kind !== 'type') shown++;   // type 块不计入"传输/安全层有没有参数要填"
  });
  return shown;
}

function syncInbound() {
  const card = document.getElementById('inOptsCard');
  if (!card) return;
  const shown = syncOpts(card, '[name=inbound_transport]', '[name=inbound_security]', '.in-opt');
  document.getElementById('inOptsNone').style.display = shown ? 'none' : '';
}

function syncOutbound(row) {
  syncOpts(row, '[name$="[out_transport]"]', '[name$="[out_security]"]', '.out-opt', '[name$="[out_type]"]');
}

function syncAll() {
  syncInbound();
  document.querySelectorAll('.out-row').forEach(syncOutbound);
}

document.addEventListener('change', e => {
  if (e.target.matches('[name=inbound_transport],[name=inbound_security]')) syncInbound();
  const row = e.target.closest('.out-row');
  if (row && e.target.matches('[name$="[out_transport]"],[name$="[out_security]"],[name$="[out_type]"]')) syncOutbound(row);
});
document.getElementById('addOut').addEventListener('click', () => setTimeout(syncAll, 0));
syncAll();
</script>
@endpush
@endsection
