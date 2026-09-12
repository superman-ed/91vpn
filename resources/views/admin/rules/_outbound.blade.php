@php
  // 同 form.blade.php：不在 Blade 的 @php 块里放带 [] 的闭包。
  $set = ($o && is_array($o->target_node_set)) ? array_map('intval', $o->target_node_set) : [];
  $cred = $o ? ($o->out_cred ?? []) : [];
  // 同上：数组字面量不写在 @foreach 里。
  // [!!] 【没有 vless】。agent 的出站构建器（internal/core/xray/relay.go）
  // 只实现了这六种；给 vless 会让 agent 拒绝【整份】规则，而不只是这一条。
  // 入站【有】vless —— 两边不对称，所以不能共用一份列表。
  $outTypes = ['direct', 'vmess', 'trojan', 'ss', 'socks', 'http'];
  $transports = ['tcp', 'ws', 'grpc'];
  $securities = ['none', 'tls', 'reality'];
  $ppModes = [0 => '不发', 1 => 'v1', 2 => 'v2'];
  $oo = $o ? ($o->out_opts ?? []) : [];
  $orl = $oo['reality'] ?? [];
  $ows = $oo['ws'] ?? [];
  $ogr = $oo['grpc'] ?? [];
  $omux = $oo['mux'] ?? [];
@endphp
<div class="out-row border rounded p-3 mb-3">
  <div class="form-row">
    <div class="form-group col-md-2">
      <label>池</label>
      <select name="outbounds[{{ $i }}][pool]" class="form-control">
        <option value="primary" @selected(($o->pool ?? 'primary') === 'primary')>主池</option>
        <option value="backup" @selected(($o->pool ?? '') === 'backup')>备池</option>
      </select>
    </div>
    <div class="form-group col-md-2">
      <label>协议</label>
      <select name="outbounds[{{ $i }}][out_type]" class="form-control">
        @foreach ($outTypes as $t)
          <option value="{{ $t }}" @selected(($o->out_type ?? 'direct') === $t)>{{ $t }}</option>
        @endforeach
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>落地节点</label>
      <select name="outbounds[{{ $i }}][target_node_set][]" class="form-control" multiple size="3">
        @foreach ($nodes as $n)
          <option value="{{ $n->id }}" @selected(in_array($n->id, $set, true))>{{ $n->label() }}</option>
        @endforeach
      </select>
      <div class="hint">选多个 = 在它们之间均衡</div>
    </div>
    <div class="form-group col-md-3">
      <label>或直接填地址</label>
      <input name="outbounds[{{ $i }}][target_addr]" class="form-control"
             value="{{ ($o->target_addr ?? '') }}" placeholder="1.2.3.4 或 域名">
      <div class="hint">选了节点就不用填这里</div>
    </div>
    <div class="form-group col-md-1">
      <label>端口</label>
      <input name="outbounds[{{ $i }}][target_port]" class="form-control" value="{{ ($o->target_port ?? '') }}">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group col-md-2">
      <label>传输</label>
      <select name="outbounds[{{ $i }}][out_transport]" class="form-control">
        <option value="">（tcp）</option>
        @foreach ($transports as $t)
          <option value="{{ $t }}" @selected(($o->out_transport ?? '') === $t)>{{ $t }}</option>
        @endforeach
      </select>
    </div>
    <div class="form-group col-md-2">
      <label>安全层</label>
      <select name="outbounds[{{ $i }}][out_security]" class="form-control sec-sel">
        <option value="">（none）</option>
        @foreach ($securities as $t)
          <option value="{{ $t }}" @selected(($o->out_security ?? '') === $t)>{{ $t }}</option>
        @endforeach
      </select>
    </div>
    <div class="form-group col-md-2">
      <label>uTLS 指纹</label>
      <input name="outbounds[{{ $i }}][fingerprint]" class="form-control"
             value="{{ ($o->fingerprint ?? '') }}" placeholder="chrome">
    </div>
    <div class="form-group col-md-2">
      <label>SNI</label>
      <input name="outbounds[{{ $i }}][sni]" class="form-control" value="{{ ($o->sni ?? '') }}">
    </div>
    <div class="form-group col-md-2">
      <label>PROXY protocol</label>
      <select name="outbounds[{{ $i }}][send_proxy_protocol]" class="form-control">
        @foreach ($ppModes as $n => $t)
          <option value="{{ $n }}" @selected((int) ($o->send_proxy_protocol ?? 0) === $n)>{{ $t }}</option>
        @endforeach
      </select>
      <div class="hint">仅 direct 出站</div>
    </div>
    <div class="form-group col-md-2">
      <label>权重</label>
      <input name="outbounds[{{ $i }}][weight]" type="number" class="form-control" value="{{ ($o->weight ?? 0) }}">
    </div>
  </div>

  {{-- `[!!]` 凭据按协议类型显隐。direct 出站【不需要任何凭据】——
       它是裸端口转发，不解协议；把三个凭据框摆在那里，人会以为该填。
       哪个类型用哪个字段，依据是 agent 的校验器
       (domain/relay/forward.go 的 Outbound.validate)，不是猜的。 --}}
  <div class="form-row">
    <div class="out-opt col-md-3 p-0" data-when="type:vmess">
      <div class="form-group px-2">
        <label>凭据 UUID</label>
        <input name="outbounds[{{ $i }}][cred_uuid]" class="form-control"
               value="{{ $cred['uuid'] ?? '' }}">
      </div>
    </div>
    <div class="out-opt col-md-3 p-0" data-when="type:trojan">
      <div class="form-group px-2">
        <label>凭据口令</label>
        <input name="outbounds[{{ $i }}][cred_password]" class="form-control"
               value="{{ $cred['password'] ?? '' }}">
      </div>
    </div>
    <div class="out-opt col-md-5 p-0" data-when="type:ss">
      <div class="form-row px-2">
        <div class="form-group col-md-7">
          <label>ss 口令</label>
          <input name="outbounds[{{ $i }}][cred_password]" class="form-control"
                 value="{{ $cred['password'] ?? '' }}">
        </div>
        <div class="form-group col-md-5">
          <label>ss 加密</label>
          <input name="outbounds[{{ $i }}][cred_cipher]" class="form-control"
                 value="{{ $cred['cipher'] ?? '' }}" placeholder="aes-128-gcm">
        </div>
      </div>
    </div>
    <div class="out-opt col-md-5 p-0" data-when="type:socks">
      <div class="form-row px-2">
        <div class="form-group col-md-6">
          <label>socks 用户名（选填）</label>
          <input name="outbounds[{{ $i }}][cred_username]" class="form-control"
                 value="{{ $cred['username'] ?? '' }}">
        </div>
        <div class="form-group col-md-6">
          <label>socks 口令（选填）</label>
          <input name="outbounds[{{ $i }}][cred_password]" class="form-control"
                 value="{{ $cred['password'] ?? '' }}">
        </div>
      </div>
    </div>
    <div class="out-opt col-md-5 p-0" data-when="type:http">
      <div class="form-row px-2">
        <div class="form-group col-md-6">
          <label>http 用户名（选填）</label>
          <input name="outbounds[{{ $i }}][cred_username]" class="form-control"
                 value="{{ $cred['username'] ?? '' }}">
        </div>
        <div class="form-group col-md-6">
          <label>http 口令（选填）</label>
          <input name="outbounds[{{ $i }}][cred_password]" class="form-control"
                 value="{{ $cred['password'] ?? '' }}">
        </div>
      </div>
    </div>
    <div class="form-group col-md-4 ml-auto d-flex align-items-end">
      <button type="button" class="btn btn-outline-danger rm-out mb-3">删除这一行</button>
    </div>
  </div>

  {{-- 出站的传输/安全层参数。跟入站一样按当前形态显示。 --}}
  <div class="out-opt" data-when="transport:ws">
    <div class="form-row">
      <div class="form-group col-md-3">
        <label>ws path</label>
        <input name="outbounds[{{ $i }}][ws_path]" class="form-control" value="{{ $ows['path'] ?? '' }}">
      </div>
      <div class="form-group col-md-3">
        <label>ws host</label>
        <input name="outbounds[{{ $i }}][ws_host]" class="form-control" value="{{ $ows['host'] ?? '' }}">
      </div>
    </div>
  </div>
  <div class="out-opt" data-when="transport:grpc">
    <div class="form-row">
      <div class="form-group col-md-4">
        <label>grpc serviceName</label>
        <input name="outbounds[{{ $i }}][grpc_service]" class="form-control" value="{{ $ogr['service_name'] ?? '' }}">
      </div>
    </div>
  </div>
  <div class="out-opt" data-when="security:reality">
    <div class="form-row">
      <div class="form-group col-md-5">
        <label>对端公钥 <span class="text-danger">*</span></label>
        <input name="outbounds[{{ $i }}][reality_pubkey]" class="form-control"
               value="{{ $orl['public_key'] ?? '' }}" placeholder="从落地那条规则的入站参数里复制">
        <div class="warn">
          没有它 agent 会拒绝<strong>整份</strong>规则，不只是这一条
        </div>
      </div>
      <div class="form-group col-md-3">
        <label>short_id</label>
        <input name="outbounds[{{ $i }}][reality_short_id]" class="form-control"
               value="{{ $orl['short_id'] ?? '' }}">
        <div class="hint">要是对端 short_ids 之一</div>
      </div>
      <div class="form-group col-md-4">
        <label>spiderX</label>
        <input name="outbounds[{{ $i }}][reality_spiderx]" class="form-control"
               value="{{ $orl['spider_x'] ?? '' }}" placeholder="/">
      </div>
    </div>
    <div class="hint">
      上面那个 <strong>SNI 必须是对端 server_names 之一</strong>，否则同样被拒。
    </div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-3">
      <label class="custom-switch mt-2">
        <input type="checkbox" name="outbounds[{{ $i }}][mux_enabled]" value="1"
               class="custom-switch-input" {{ ($omux['enabled'] ?? false) ? 'checked' : '' }}>
        <span class="custom-switch-indicator"></span>
        <span class="custom-switch-description">开启 mux</span>
      </label>
    </div>
    <div class="form-group col-md-2">
      <label>并发数</label>
      <input name="outbounds[{{ $i }}][mux_concurrency]" type="number" class="form-control"
             value="{{ $omux['concurrency'] ?? 8 }}">
    </div>
    <div class="form-group col-md-7">
      <div class="hint mt-4">
        mux 让多条用户连接复用少数几条隧道连接。<strong>抗封上有利</strong>
        （连接数少、建连节奏不像代理），代价是队头阻塞 —— 一条卡住会拖慢同隧道的其它连接。
      </div>
    </div>
  </div>

  {{-- 抗封约束 A：这个开关直接决定节点认不认这条规则，所以后果要写在旁边。 --}}
  <div class="alert alert-secondary mb-0">
    <label class="custom-switch mb-1">
      <input type="checkbox" name="outbounds[{{ $i }}][trusted_transit]" value="1"
             class="custom-switch-input trusted" {{ ($o->trusted_transit ?? '') ? 'checked' : '' }}>
      <span class="custom-switch-indicator"></span>
      <span class="custom-switch-description"><strong>这一跳走专线/内网，不面对防火墙</strong></span>
    </label>
    <div class="hint mb-0">
      不勾这个，就<strong>必须</strong>把上面的安全层设成 tls 或 reality，否则
      <strong>节点会拒绝整条规则</strong>。<br>
      这是刻意的：<code>[I]</code> 无伪装的跳更容易被流量特征识别 —— 这是普遍认识，
      我们<strong>没有实测</strong>。之所以做成硬性拒绝而不是提示，是因为一旦出事，
      现象只是"节点又被封了"，极难查到是哪条规则。
      确实不面对审查（IPLC / 专线 / 内网）就勾上，等于明确说出"我知道这条链路安全"。
    </div>
  </div>
</div>
