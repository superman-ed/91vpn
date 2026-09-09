{{-- 一键部署（ADR-008 P4b：从 relaypanel 并入）。
     [!] 按钮 + 模态框 + JS 三块原样搬来，只把 URL 前缀改成 /admin。
     凭据只在这一次请求里存在：后端用管道喂给脱离出去的后台进程，用完即焚。 --}}

@once
@push('scripts')
{{-- JS 只需注入一次 --}}
@endpush
@endonce

<div class="modal fade" id="deployModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">一键部署 —— <span id="dpNodeName"></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="关闭"><span>&times;</span></button>
      </div>

      {{-- 步骤1：填参数 --}}
      <div class="modal-body" id="dpForm">
        <div class="alert alert-warning py-2 px-3" style="font-size:.85rem">
          私钥<strong>一次性使用、绝不保存</strong>：只经内存交给后台进程，装完即随进程消失。
          目标机需已开机、能被本面板 SSH 到（建议节点防火墙只放行本面板 IP）。
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>SSH 地址</label>
            <input id="dpHost" class="form-control form-control-sm" placeholder="1.2.3.4">
          </div>
          <div class="form-group col-md-3">
            <label>端口</label>
            <input id="dpPort" class="form-control form-control-sm" value="22">
          </div>
          <div class="form-group col-md-3">
            <label>用户</label>
            <input id="dpUser" class="form-control form-control-sm" value="root">
          </div>
        </div>
        <div class="form-group">
          <label>二进制根地址 <span class="hint">（目标机自己 curl：<code>&lt;根&gt;/agent-linux-&lt;arch&gt;</code> 与 <code>&lt;根&gt;/install.sh</code>）</span></label>
          <input id="dpBase" class="form-control form-control-sm" placeholder="https://dl.example.com/agent/v1">
        </div>

        {{-- 落地专属：连 91vpn 的身份 + accept_proxy 防火墙。中转不显示。 --}}
        <div id="dpLandingFields" class="d-none">
          <div class="alert alert-info py-2 px-3" style="font-size:.83rem">
            落地走 <strong>面板模式</strong>：连 91vpn 拉 REALITY / 协议 / accept_proxy 配置。
            下面三项从 <strong>91vpn 后台该节点</strong>复制过来。
          </div>
          <div class="form-row">
            <div class="form-group col-md-7">
              <label>91vpn 面板地址</label>
              <input id="dpApiUrl" class="form-control form-control-sm" placeholder="https://api.91vpn.example">
            </div>
            <div class="form-group col-md-2">
              <label>node id</label>
              <input id="dpNodeId" class="form-control form-control-sm" placeholder="12">
            </div>
            <div class="form-group col-md-3">
              <label>协议</label>
              <select id="dpServerType" class="form-control form-control-sm">
                <option value="vless">vless</option>
                <option value="vmess">vmess</option>
                <option value="trojan">trojan</option>
                <option value="shadowsocks">shadowsocks</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>91vpn 节点 secret <span class="hint">（该节点在 91vpn 的 key，用来拉配置）</span></label>
            <input id="dpApiKey" type="password" class="form-control form-control-sm" autocomplete="off">
          </div>
          {{-- accept_proxy 开时才要的防火墙参数 --}}
          <div id="dpFwFields" class="d-none">
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>落地入站端口</label>
                <input id="dpProxyPort" class="form-control form-control-sm" placeholder="443">
              </div>
              <div class="form-group col-md-9">
                <label>允许的中转源 IP <span class="hint">（逗号分隔，已按拓扑预填；防火墙只放行这些）</span></label>
                <input id="dpAllowSrc" class="form-control form-control-sm" placeholder="1.2.3.4,5.6.7.8">
              </div>
            </div>
            <div class="hint mb-2" style="color:#b45309">
              ⚠️ 开了 accept_proxy 必须锁防火墙：PROXY 头无认证，不锁源 IP 则客户端真实 IP 可被伪造。装完会自动下 iptables（只碰这个端口，不动 22）。
            </div>
          </div>
        </div>
        <div class="form-group">
          <label class="mr-3">认证方式</label>
          <label class="mr-3"><input type="radio" name="dpAuth" value="key" checked> 私钥</label>
          <label><input type="radio" name="dpAuth" value="password"> 密码</label>
        </div>
        <div class="form-group" id="dpKeyWrap">
          <label>SSH 私钥（粘贴 <code>-----BEGIN ... PRIVATE KEY-----</code> 全文）</label>
          <textarea id="dpKey" class="form-control form-control-sm" rows="6"
                    style="font-family:monospace;font-size:.78rem"></textarea>
        </div>
        <div class="form-group d-none" id="dpPassWrap">
          <label>SSH 密码</label>
          <input id="dpPass" type="password" class="form-control form-control-sm" autocomplete="off">
        </div>
        <div class="form-group mb-0">
          <label><input type="checkbox" id="dpAcceptProxy"> 入站开启 PROXY protocol（<span class="hint">仅【专收上游中转流量】的节点勾；面向用户直连的别勾</span>）</label>
        </div>
      </div>

      {{-- 步骤2：实时日志 --}}
      <div class="modal-body d-none" id="dpProgress">
        <div class="mb-2">
          状态：<span id="dpStatus" class="adm-pill muted">准备中</span>
          <span id="dpReason" class="warn"></span>
        </div>
        <pre id="dpLog" style="background:#0f172a;color:#cbd5e1;padding:12px;border-radius:6px;
             max-height:340px;overflow:auto;font-size:.78rem;white-space:pre-wrap;margin:0"></pre>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">关闭</button>
        <button type="button" class="btn adm-btn" id="dpGo"><i class="fas fa-rocket mr-1"></i>开始部署</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var node = null, timer = null;

  // [!] stisla 的内容容器带 transform/定位，形成层叠上下文，Bootstrap 的灰色
  //     遮罩(追加到 body)会盖在弹窗上面 —— 表现为"弹出一片灰、点不动"。
  //     把弹窗挪到 body 直下，脱离那个上下文即可。
  jQuery('#deployModal').appendTo('body');

  function $(id){ return document.getElementById(id); }
  function show(el, on){ el.classList.toggle('d-none', !on); }

  // 打开弹窗，回填目标节点
  document.querySelectorAll('.js-deploy').forEach(function (btn) {
    btn.addEventListener('click', function () {
      node = { id: btn.dataset.node, name: btn.dataset.name, role: btn.dataset.role };
      $('dpNodeName').textContent = '#' + node.id + ' ' + node.name + '（' + node.role + '）';
      $('dpHost').value = btn.dataset.host || '';
      $('dpPort').value = '22'; $('dpUser').value = 'root';
      $('dpKey').value = ''; $('dpPass').value = '';
      $('dpLog').textContent = ''; $('dpReason').textContent = '';

      // 落地才显示 91vpn 身份 + 防火墙字段；预填端口与中转源 IP。
      var landing = node.role === 'landing';
      show($('dpLandingFields'), landing);
      if (landing) {
        $('dpApiUrl').value = ''; $('dpNodeId').value = ''; $('dpApiKey').value = '';
        $('dpServerType').value = 'vless';
        $('dpProxyPort').value = btn.dataset.port && btn.dataset.port !== '0' ? btn.dataset.port : '';
        $('dpAllowSrc').value = btn.dataset.src || '';
      }
      // accept_proxy 默认：落地(B 拓扑)勾上，中转不勾。
      $('dpAcceptProxy').checked = landing;
      syncFw();

      show($('dpForm'), true); show($('dpProgress'), false);
      $('dpGo').disabled = false; $('dpGo').innerHTML = '<i class="fas fa-rocket mr-1"></i>开始部署';
      if (timer) { clearInterval(timer); timer = null; }
      jQuery('#deployModal').modal('show');
    });
  });

  // accept_proxy 勾了、且是落地时，才显示防火墙(端口/源IP)字段。
  function syncFw() {
    show($('dpFwFields'), node && node.role === 'landing' && $('dpAcceptProxy').checked);
  }
  $('dpAcceptProxy').addEventListener('change', syncFw);

  // 私钥 / 密码切换
  document.querySelectorAll('input[name="dpAuth"]').forEach(function (r) {
    r.addEventListener('change', function () {
      var key = document.querySelector('input[name="dpAuth"]:checked').value === 'key';
      show($('dpKeyWrap'), key); show($('dpPassWrap'), !key);
    });
  });

  function setStatus(s) {
    var el = $('dpStatus'), m = {pending:['muted','排队中'], running:['warn','部署中…'],
      ok:['ok','已就绪'], failed:['danger','失败']};
    var v = m[s] || ['muted', s];
    el.className = 'adm-pill ' + v[0]; el.textContent = v[1];
  }

  function poll() {
    fetch('/admin/nodes/' + node.id + '/deploy/' + node.run, {headers: {'Accept':'application/json'}})
      .then(function (r) { return r.json(); })
      .then(function (d) {
        $('dpLog').textContent = d.log || '';
        $('dpLog').scrollTop = $('dpLog').scrollHeight;
        setStatus(d.status);
        $('dpReason').textContent = d.reason || '';
        if (!d.running) {
          clearInterval(timer); timer = null;
          $('dpGo').classList.remove('d-none'); $('dpGo').disabled = false;
          $('dpGo').innerHTML = '<i class="fas fa-redo mr-1"></i>再次部署';
        }
      })
      .catch(function () {/* 网络抖动，下个周期再来 */});
  }

  $('dpGo').addEventListener('click', function () {
    var auth = document.querySelector('input[name="dpAuth"]:checked').value;
    var secret = auth === 'key' ? $('dpKey').value : $('dpPass').value;
    if (!$('dpHost').value.trim()) { alert('填一下 SSH 地址'); return; }
    if (!$('dpBase').value.trim()) { alert('填一下二进制根地址'); return; }
    if (!secret.trim()) { alert('粘一下私钥或填密码'); return; }

    var body = {
      ssh_host: $('dpHost').value.trim(), ssh_port: parseInt($('dpPort').value, 10) || 22,
      ssh_user: $('dpUser').value.trim() || 'root', auth_mode: auth, secret: secret,
      base_url: $('dpBase').value.trim(), accept_proxy: $('dpAcceptProxy').checked
    };
    if (node.role === 'landing') {
      if (!$('dpApiUrl').value.trim() || !$('dpNodeId').value.trim() || !$('dpApiKey').value) {
        alert('落地要填 91vpn 面板地址、node id 和 secret'); return;
      }
      body.api_url = $('dpApiUrl').value.trim();
      body.node_id = parseInt($('dpNodeId').value, 10);
      body.server_type = $('dpServerType').value;
      body.api_key = $('dpApiKey').value;
      body.proxy_port = parseInt($('dpProxyPort').value, 10) || null;
      body.allow_src = $('dpAllowSrc').value.trim();
    }

    $('dpGo').disabled = true;
    show($('dpForm'), false); show($('dpProgress'), true);
    $('dpLog').textContent = '==> 提交部署请求…\n'; setStatus('pending');

    fetch('/admin/nodes/' + node.id + '/deploy', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body: JSON.stringify(body)
    })
    .then(function (r) { return r.json().then(function (j) { return {ok: r.ok, j: j}; }); })
    .then(function (res) {
      // 密钥用完立刻从 DOM 清掉，不留在页面上
      $('dpKey').value = ''; $('dpPass').value = ''; $('dpApiKey').value = '';
      if (!res.ok) {
        var msg = res.j && res.j.message ? res.j.message : '提交失败';
        if (res.j && res.j.errors) msg = Object.values(res.j.errors).map(function(e){return e[0];}).join('；');
        $('dpLog').textContent += '❌ ' + msg + '\n'; setStatus('failed');
        $('dpGo').disabled = false; return;
      }
      node.run = res.j.run_id;
      timer = setInterval(poll, 1500); poll();
    })
    .catch(function () { $('dpLog').textContent += '❌ 网络错误，未能提交\n'; setStatus('failed'); $('dpGo').disabled = false; });
  });
})();
</script>
@endpush
