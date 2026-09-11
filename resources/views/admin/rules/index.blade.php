@extends('layouts.admin')
@section('title', '转发规则')
@section('content')
<div class="adm-head">
  <h4>转发规则</h4>
  <div class="adm-tools">
    <form method="get" class="adm-search d-flex" style="gap:8px">
      <input name="q" value="{{ $kw }}" class="form-control form-control-sm"
             placeholder="按名称或端口搜索" style="min-width:200px">
      @if ($kw)<a href="/admin/rules" class="btn btn-sm btn-light">清除</a>@endif
    </form>
    {{-- 向导排在前面:常见形态走它,而且它会把"落地收 PROXY 头"那一步一并做掉。 --}}
    <a href="/admin/rules/wizard" class="btn btn-success mr-2"><i class="fas fa-magic mr-1"></i>中转链路向导</a>
    <a href="/admin/rules/create" class="btn adm-btn"><i class="fas fa-plus mr-1"></i>新建规则（完整表单）</a>
  </div>
</div>

<div class="card adm-panel mb-4">
  <div class="card-body">
    <table class="table adm-table">
      <thead>
        <tr>
          <th>#</th><th>名称</th><th>入站</th><th>出站</th>
          <th>均衡</th><th>近 7 天流量</th><th>健康检查</th><th>状态</th>
          <th class="text-right">操作</th>
        </tr>
      </thead>
      <tbody>
      @forelse ($rules as $r)
        @php
          // 用统一的检查器，而不是只看抗封那一条 —— 拒绝的原因有十几种。
          $probs = collect($problems[$r->id] ?? []);
          $rejects = $probs->where('level', 'reject');
          $brokens = $probs->where('level', 'broken');
          // `[!]` 未知也要露出来：PROXY 头配对查不到落地状态时，
          // 沉默等于告诉运维"没问题"，而这正是它最危险的地方。
          // `[!]` UDP 那条单独拎出来：它也含"PROXY 头"三个字，会被下面的
          // $unsure 顺手抓走,渲染成带问号的"未知"样式 —— 而它不是未知,
          // 是确定会发生的事(节点必定关掉本规则的 UDP)。
          $udpOff  = $probs->where('level', 'warn')->filter(
              fn ($x) => str_contains($x['text'], 'UDP 关掉'));
          $unsure  = $probs->where('level', 'warn')->filter(
              fn ($x) => ! str_contains($x['text'], 'UDP 关掉')
                  && (str_contains($x['text'], 'PROXY 头') || str_contains($x['text'], 'accept_proxy')));
          $inNodes = collect($r->inbound_node_set ?? [])
              ->map(fn ($id) => $nodes[$id]->name ?? "#$id");
          $backup = $r->outbounds->where('pool', 'backup');
        @endphp
        <tr>
          <td>{{ $r->id }}</td>
          <td>
            <strong>{{ $r->name }}</strong>
            @if ($r->speed_limit)
              <span class="adm-pill info ml-1">{{ $r->speed_limit }} Mbps</span>
            @endif
            @if ($rejects->isNotEmpty())
              <div class="warn">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>节点会拒绝</strong>：{{ $rejects->first()['text'] }}
                @if ($rejects->count() > 1)（另有 {{ $rejects->count() - 1 }} 项）@endif
              </div>
            @endif
            @if ($brokens->isNotEmpty())
              <div class="warn">
                <i class="fas fa-unlink"></i>
                <strong>会下发但用户连不上</strong>：{{ $brokens->first()['text'] }}
                @if ($brokens->count() > 1)（另有 {{ $brokens->count() - 1 }} 项）@endif
                <div class="hint">PROXY 头配对错开时两端都不报错 —— 只有客户端知道</div>
              </div>
            @elseif ($unsure->isNotEmpty())
              <div class="hint mt-1">
                <i class="fas fa-question-circle"></i>
                {{ $unsure->first()['text'] }}
              </div>
            @endif
            {{-- `[!]` 与上面两档分开显示：它不是"会不会出事"的判断,
                 而是一件【已经确定】的事 —— 这条规则上没有 UDP。
                 只跑 TCP 的规则完全不受影响,所以用中性的提示样式。 --}}
            @if ($udpOff->isNotEmpty())
              <div class="hint mt-1">
                <i class="fas fa-ban"></i>
                本规则不承载 UDP（出站发 PROXY 头，节点会关掉 UDP）
              </div>
            @endif
            {{-- `[!!]` 上面那条是**预演**（我们算出来节点会拒绝），
                 这一条是**实测**（节点自己报回来的）。两者都要有：
                 预演漏检的情形，只有节点的回报能发现。

                 `[!]` 措辞是节点级的 —— 节点报的指纹覆盖它身上全部规则，
                 说"这条规则未生效"是在编造一个我们并不掌握的事实。 --}}
            @if (! empty($lag[$r->id]))
              <div class="warn">
                <i class="fas fa-clock"></i>
                <strong>节点没跟上下发</strong>：{{ implode('、', $lag[$r->id]) }}
                <div class="hint">该节点跑的还不是面板当下这一份，去节点页看原因</div>
              </div>
            @endif
          </td>
          <td>
            <span class="adm-pill primary">{{ $r->inbound_type }}</span>
            @if ($r->inbound_security && $r->inbound_security !== 'none')
              <span class="adm-pill info">{{ $r->inbound_security }}</span>
            @endif
            @if ($r->accept_proxy_protocol)
              <span class="adm-pill warn">收 PROXY</span>
            @endif
            <div class="hint">:{{ $r->listen_port }} @ {{ $inNodes->implode('、') ?: '未指定节点' }}</div>
          </td>
          <td>
            @foreach ($r->outbounds->where('pool', 'primary') as $o)
              <div>
                <span class="adm-pill muted">{{ $o->out_type }}</span>
                <i class="fas fa-arrow-right mx-1" style="font-size:10px;color:#98a6ad"></i>
                {{ $o->target_addr ?: collect($o->target_node_set ?? [])->map(fn ($id) => $nodes[$id]->name ?? "#$id")->implode('、') }}
                @if ($o->trusted_transit)<span class="adm-pill muted ml-1">专线</span>@endif
              </div>
            @endforeach
            @if ($backup->count())
              <div class="hint">备池 {{ $backup->count() }} 个（{{ $r->backup_balance }}）</div>
            @endif
          </td>
          <td><span class="adm-pill info">{{ $r->balance }}</span></td>
          <td>
            @php $tf = $traffic[$r->id] ?? null; @endphp
            @if ($tf && ($tf->u + $tf->d) > 0)
              <strong>{{ \App\Support\Fmt::bytes($tf->u + $tf->d) }}</strong>
              <div class="hint">↑{{ \App\Support\Fmt::bytes($tf->u, 1) }}
                  ↓{{ \App\Support\Fmt::bytes($tf->d, 1) }}</div>
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
          <td>
            @if ($r->hc_enabled)
              <span class="adm-pill ok">开</span>
              <div class="hint">{{ $r->hc_interval_sec }}s / 失败{{ $r->hc_max_fail }} / 恢复{{ $r->hc_max_success }}</div>
            @else
              <span class="adm-pill muted">关</span>
              @if ($r->outbounds->where('pool', 'primary')->count() > 1)
                <div class="warn">多出站且关了健康检查 —— 死掉的上游仍会被轮到</div>
              @endif
            @endif
          </td>
          <td>
            @if ($r->enabled)
              <span class="adm-pill ok">启用</span>
            @else
              <span class="adm-pill muted">停用</span>
            @endif
          </td>
          <td class="text-right" style="white-space:nowrap">
            <a href="/admin/rules/{{ $r->id }}/edit" class="btn btn-sm btn-outline-primary">编辑</a>
            <form action="/admin/rules/{{ $r->id }}" method="post" class="d-inline"
                  onsubmit="return confirm('删除规则「{{ $r->name }}」？节点会在下一个拉取周期内停止这条转发。')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger">删除</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="adm-empty">
          @if ($rules->total() > 0)
            {{-- `[!!]` 越界的页码要说"这一页没有内容"，不能说"还没有" ——
                 后者是在陈述一个假事实，会让人以为数据丢了。 --}}
            这一页没有内容（共 {{ $rules->total() }} 条）。
            <a href="/admin/rules">回到第一页</a>
          @elseif ($kw)
            没有匹配「{{ $kw }}」的规则。<a href="/admin/rules">清除搜索</a>
          @else
            还没有转发规则。推荐用<a href="/admin/rules/wizard">中转链路向导</a>，
            它会把"落地收 PROXY 头"那一步一并配好 —— 那一步漏了两端都不报错。
          @endif
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  @if ($rules->hasPages())
    <div class="adm-foot">
      <span class="hint">共 {{ $rules->total() }} 条</span>
      {{ $rules->links() }}
    </div>
  @endif
</div>

<div class="card adm-panel">
  <div class="card-body" style="padding:20px 22px">
    <h6>规则是怎么到节点上的</h6>
    <p class="hint mb-1">
      规则存在这里，由本面板的 <code>GET /mod_mu/nodes/{id}/routes</code> 直接下发给节点。
      节点每个拉取周期（跟随 <code>check_interval</code>）检查一次。
    </p>
    <p class="hint mb-1">
      <strong>只改出站地址</strong>的变更是热更新 —— 正在用的连接不会断。
      改入站形态、增删规则、改出站个数则需要节点重启内核，会断开连接。
    </p>
    <p class="hint mb-1">
      <strong>「近 7 天流量」是归因，不是实时用量。</strong>
      中转下行走零拷贝（splice），内核直接搬数据，只能在连接结束时结算 ——
      所以长连接的下行会滞后到它断开才计入。要看"这台机现在用了多少"，
      请看<a href="/admin/nodes">节点</a>页的本周期流量（那是网卡计数，没有滞后）。
    </p>
    <p class="hint mb-0">
      只有角色为 <code>relay / springboard / front / both</code> 的节点会拿到规则；
      落地节点请求这个端点会得到 404。角色在<a href="/admin/nodes">节点与角色</a>里改。
    </p>
  </div>
</div>
@endsection
