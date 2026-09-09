@extends('layouts.admin')
@section('title', '在线 IP')
@section('content')
<div class="adm-head">
  <h4>在线 IP</h4>
  <div class="adm-tools"><span class="adm-pill primary">{{ $total }} 个来源</span></div>
</div>

{{-- `[!!]` 这句必须摆在最前面。看到"在线 IP"三个字，人会本能地以为
     能查到"谁在用"，而中转根本不知道。不写清楚，迟早有人拿它去对用户
     做判断。 --}}
<div class="info-note mb-4">
  这里是<strong>来源地址</strong>，不是用户。中转不认证用户（只透传字节），
  它看得到的只有 TCP 连接从哪来。要知道是哪个用户在用，去 91vpn 看落地节点。
  <br>
  超过 <strong>{{ $staleMinutes }} 分钟</strong>没再出现的来源不显示。
  一个来源可能对应多个连接，也可能是一整个 NAT 后面的很多人。
  <br>
  <strong>只统计握手成功、真正进入转发的连接。</strong>
  端口扫描和握手失败的尝试不会出现 —— 好处是这一页不会被扫描刷满，
  代价是它答不了"有没有人在扫我"（那要看节点的内核日志）。
</div>

<div class="card adm-panel mb-3">
  <div class="card-body">
    <table class="table adm-table">
      <thead><tr><th>规则</th><th>节点</th><th>来源数</th><th>最近一次</th><th></th></tr></thead>
      <tbody>
      @forelse ($summary as $g)
        @php $active = $rid === (int) $g->rule_id && $nid === (int) $g->node_id; @endphp
        <tr @if($active) style="background:#f4f6ff" @endif>
          <td><strong>{{ $ruleNames[$g->rule_id] ?? "#{$g->rule_id}（已删除）" }}</strong></td>
          <td>{{ $nodeNames[$g->node_id] ?? "#{$g->node_id}" }}</td>
          <td><span class="adm-pill info">{{ $g->n }}</span></td>
          <td class="hint">{{ \Illuminate\Support\Carbon::parse($g->newest)->diffForHumans() }}</td>
          <td class="text-right">
            @if ($active)
              <a href="/admin/relay/online-ip" class="btn btn-sm btn-light">收起</a>
            @else
              <a href="/admin/relay/online-ip?rule={{ $g->rule_id }}&node={{ $g->node_id }}"
                 class="btn btn-sm btn-outline-primary">看明细</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="adm-empty">
          最近 {{ $staleMinutes }} 分钟没有来源。<br>
          <span class="hint">
            中转空闲时这是正常的。如果确信有人在用却看不到，
            检查节点是否在上报 —— 概览页的心跳能说明它还活着。
          </span>
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

@if ($detail)
  <div class="card adm-panel">
    <div class="card-header px-4 py-3 border-0">
      <h4 class="mb-0" style="font-size:15px;font-weight:700;color:#34395e">
        {{ $ruleNames[$rid] ?? "#$rid" }}
        <span class="hint" style="font-weight:400">@ {{ $nodeNames[$nid] ?? "#$nid" }}</span>
      </h4>
    </div>
    <div class="card-body">
      <table class="table adm-table">
        <thead><tr><th>来源 IP</th><th>最后出现</th><th></th></tr></thead>
        <tbody>
        @foreach ($detail as $r)
          @php $ago = $r->last_seen->diffInSeconds(now()); @endphp
          <tr>
            <td><code>{{ $r->ip }}</code></td>
            <td>{{ $r->last_seen->format('H:i:s') }}</td>
            <td>
              @if ($ago < 120)
                <span class="adm-pill ok">{{ $ago }} 秒前</span>
              @else
                <span class="adm-pill muted">{{ intdiv($ago, 60) }} 分钟前</span>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @if ($detail->hasPages())
      <div class="adm-foot">
        <span class="hint">共 {{ $detail->total() }} 个来源</span>
        {{ $detail->links() }}
      </div>
    @endif
  </div>
@endif
@endsection
