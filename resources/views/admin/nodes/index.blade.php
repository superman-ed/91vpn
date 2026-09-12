@extends('layouts.admin')
@section('title', '节点管理')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-server text-primary"></i> 节点管理 <span class="text-muted" style="font-size:13px;font-weight:400">共 {{ $nodes->count() }} 个</span></h4>
    <a href="/admin/nodes/create" class="btn adm-btn"><i class="fas fa-plus"></i> 添加节点</a>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>ID</th><th>名称</th><th>角色</th><th>地址:端口</th><th>协议</th><th>倍率</th><th>流量(今日/累计)</th><th>整机额度</th><th>等级门槛</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($nodes as $n)
            <tr>
                <td class="text-muted">#{{ $n->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $n->name }}</td>
                {{-- `[!!]` 合并之后中转与落地在同一张表里(ADR-008)，不标角色就只能靠名字猜，
                     而这两类节点的运维含义完全不同：中转【拿不到用户名单】(D-1)、
                     端口来自转发规则、不进任何人的订阅。 --}}
                <td>@if($n->role === 'landing')<span class="adm-pill ok">落地</span>
                    @else<span class="adm-pill warn" title="中转类角色不认证用户、不持有用户名单(D-1)">{{ ['relay'=>'中转','springboard'=>'跳板','front'=>'入口','both'=>'落地+中转'][$n->role] ?? $n->role }}</span>@endif</td>
                <td><span style="font-family:SFMono-Regular,Menlo,Consolas,monospace">{{ $n->server }}@if((int) $n->port > 0):{{ $n->port }}@endif</span>
                    {{-- `[!]` 中转的 port 恒为 0(监听来自转发规则)。写成 "1.2.3.4:0"
                         看着像配错了，实际是对的 —— 所以干脆不显示那个 0。 --}}
                    @if((int) $n->port === 0)<span class="text-muted" style="font-size:12px">端口见转发规则</span>@endif</td>
                <td><span class="adm-pill primary">{{ strtoupper($n->type) }}</span> <span class="adm-pill muted">{{ strtoupper($n->net) }}</span></td>
                <td><span class="adm-pill {{ $n->traffic_rate <= 1 ? 'ok' : 'warn' }}">{{ rtrim(rtrim(number_format($n->traffic_rate, 2), '0'), '.') }}x</span></td>
                <td>
                    @php $tt = $todayByNode->get($n->id); $tot = $totalByNode->get($n->id); @endphp
                    <div style="font-weight:600;color:#34395e">{{ human_bytes($tt->raw ?? 0) }}</div>
                    <div class="text-muted" style="font-size:12px">累计 {{ human_bytes($tot->raw ?? 0) }}@if(($tot->billed ?? 0) > 0)<span title="计费流量(原始×倍率)"> · 计费 {{ human_bytes($tot->billed) }}</span>@endif</div>
                </td>
                {{-- `[!!]` 额度此前只能在表单里【设】，没有任何页面显示【用了多少】——
                     而额度的用途正是防机房超量账单，看不见等于没设。
                     Node::quotaPercent() 早就写好了，一直没有调用方。 --}}
                <td>
                    @php
                        $qgb = (int) ($n->quota_gb ?? 0);
                        $used = $periodBytes[$n->id] ?? 0;
                        $qp = $qgb > 0 ? $used / ($qgb * 1024 ** 3) * 100 : null;
                    @endphp
                    @if($qp === null)<span class="text-muted">不限</span>
                    @else
                        <span class="adm-pill {{ $qp >= 90 ? 'danger' : ($qp >= 70 ? 'warn' : 'ok') }}"
                              title="本计费周期整机网卡用量 / {{ $n->quota_gb }} GB，每月 {{ $n->quota_reset_day ?: 1 }} 号重置">
                            {{ number_format($qp, 0) }}%
                        </span>
                        <div class="text-muted" style="font-size:12px">{{ \App\Support\Fmt::bytes($used, 1) }} / {{ $n->quota_gb }} GB</div>
                    @endif
                </td>
                <td>@if($n->node_class > 0)<span class="adm-pill primary">{{ class_name($n->node_class) }}+</span>@else<span class="text-muted">不限</span>@endif</td>
                <td>@if($n->online)<span class="adm-pill ok">在线</span>@else<span class="adm-pill danger">离线</span>@endif
                    {{-- `[!!]` dest 失效是【静默】的:端口照常监听、这里照常显示"在线",
                         而没有任何客户端能完成握手。所以 dest 挂了必须单独标出来。 --}}
                    {{-- `[!]` dest 共用要在列表上看得见:逐个点进去才发现"撞车"
                         的话,撞了也不会有人发现。 --}}
                    @if(($destShared[$n->reality_dest] ?? 0) > 1)
                        <span class="adm-pill warn" title="{{ $n->reality_dest }} 被 {{ $destShared[$n->reality_dest] }} 台落地共用 —— 一次识别全灭，且负载叠加">dest 撞车 ×{{ $destShared[$n->reality_dest] }}</span>
                    @endif
                    @if($n->destHealth() === 'ok' && $n->reported_dest_degraded)
                        {{-- `[!]` 劣化:每一项检查都绿,而每条用户新连接都在多付时间。 --}}
                        <span class="adm-pill warn" title="{{ $n->reported_dest }} 探测时延中位 {{ $n->reported_dest_latency_ms }}ms —— 加在每条用户新连接上">dest 变慢 {{ $n->reported_dest_latency_ms }}ms</span>
                    @elseif($n->destHealth() === 'down')
                        <span class="adm-pill danger" title="{{ $n->reported_dest }} 连续失败 {{ $n->reported_dest_failures }} 次">dest 不可达</span>
                    @elseif($n->usesReality() && $n->destHealth() === 'unknown')
                        <span class="adm-pill" title="节点没报过 dest 探活,或上报已过期">dest ?</span>
                    @endif</td>
                <td>
                    <a href="/admin/nodes/{{ $n->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                    {{-- `[!!]` 一键诊断:这些线索本来就查得到,但分别在节点列表、规则列表、
                         订阅和节点机的 journalctl 里 —— 而故障往往是"某一项绿着,
                         另一项才是真因"。最典型的是端口没放行:心跳正常、面板显示在线,
                         只有客户端连不上。 --}}
                    <button type="button" class="btn btn-sm btn-outline-info js-dx"
                            data-node="{{ $n->id }}" data-name="{{ $n->name }}">
                        <i class="fas fa-stethoscope"></i> 诊断
                    </button>
                    {{-- 一键部署（ADR-008 P4b）--}}
              <button type="button" class="btn btn-sm btn-outline-success js-deploy"
                      data-node="{{ $n->id }}" data-name="{{ $n->name }}"
                      data-host="{{ $n->server }}" data-role="{{ $n->role }}"
                      data-port="{{ $n->port }}"
                      data-src="{{ implode(',', $landingSrc[$n->id] ?? []) }}">
                <i class="fas fa-rocket mr-1"></i>部署
              </button>
                    <form method="POST" action="/admin/nodes/{{ $n->id }}" class="d-inline" data-dgr="删除节点「{{ $n->name }}」后，连接该节点的用户将立即无法使用，此操作不可撤销。" data-dgr-word="{{ $n->name }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="11"><div class="adm-empty"><i class="fas fa-server fa-2x mb-2 d-block"></i>暂无节点，点右上角「添加节点」</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@include('admin.nodes._deploy')

{{-- 诊断结果弹窗 --}}
<div class="modal fade" id="dxModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">诊断 <span id="dxName" class="text-muted"></span></h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body" id="dxBody">
        <div class="text-center text-muted py-4">
          <i class="fas fa-spinner fa-spin fa-2x"></i>
          <div class="mt-2" style="font-size:13px">正在检查（探端口最多几秒）…</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var LV = {
    ok:      { cls: 'success', icon: 'check-circle',        txt: '正常' },
    warn:    { cls: 'warning', icon: 'exclamation-circle',  txt: '注意' },
    bad:     { cls: 'danger',  icon: 'times-circle',        txt: '有问题' },
    unknown: { cls: 'secondary', icon: 'question-circle',   txt: '查不了' }
  };

  document.querySelectorAll('.js-dx').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('dxName').textContent = b.dataset.name;
      document.getElementById('dxBody').innerHTML =
        '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin fa-2x"></i>' +
        '<div class="mt-2" style="font-size:13px">正在检查（探端口最多几秒）…</div></div>';
      $('#dxModal').modal('show');

      fetch('/admin/nodes/' + b.dataset.node + '/diagnose', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var bad = d.items.filter(function (i) { return i.level === 'bad'; }).length;
          var html = '';
          // `[!]` 先给一句总结:人打开弹窗想知道的是"有没有事",不是逐项读。
          html += bad
            ? '<div class="alert alert-danger py-2"><strong>' + bad + ' 项有问题</strong>，见下方红色条目</div>'
            : '<div class="alert alert-success py-2"><strong>没发现问题</strong>（不代表用户那边一定能连上 —— ' +
              '端口探测是从面板这台机器发起的，中间可能还有别的阻断）</div>';

          d.items.forEach(function (i) {
            var lv = LV[i.level] || LV.unknown;
            html += '<div class="d-flex mb-2 p-2" style="background:#f8f9fc;border-radius:6px">' +
              '<div class="mr-2 text-' + lv.cls + '"><i class="fas fa-' + lv.icon + '"></i></div>' +
              '<div style="flex:1;min-width:0">' +
              '<div><strong>' + i.title + '</strong> ' +
              '<span class="badge badge-' + lv.cls + '">' + lv.txt + '</span></div>' +
              '<div class="text-muted" style="font-size:13px;line-height:1.6">' + i.detail + '</div>' +
              '</div></div>';
          });
          html += '<div class="text-muted mt-2" style="font-size:11.5px">检查于 ' + d.at + '</div>';
          document.getElementById('dxBody').innerHTML = html;
        })
        .catch(function () {
          document.getElementById('dxBody').innerHTML =
            '<div class="alert alert-danger">诊断请求失败 —— 刷新页面再试；' +
            '频繁点击会被限流（每分钟 20 次）</div>';
        });
    });
  });
})();
</script>
