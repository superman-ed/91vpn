@extends('layouts.admin')
@section('title', '拓扑')
@section('head')
<style>
.tp-land{border:1px solid #e3e6ec;border-radius:6px;margin-bottom:14px;background:#fff}
.tp-head{padding:12px 16px;border-bottom:1px solid #eef0f4;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.tp-name{font-weight:700}
.tp-paths{padding:6px 16px 12px}
.tp-path{display:flex;align-items:center;gap:8px;padding:8px 0;border-top:1px dashed #eef0f4;flex-wrap:wrap;font-size:14px}
.tp-path:first-child{border-top:none}
.tp-seg{display:inline-flex;align-items:center;gap:6px;padding:3px 9px;border-radius:4px;background:#f5f7fa;white-space:nowrap}
.tp-seg.bad{background:#fdecea;color:#b4453c}
.tp-seg.warn{background:#fdf3e2;color:#9a6a12}
.tp-arrow{color:#aab2bd}
.tp-none{padding:14px 16px;color:#b4453c;background:#fdecea;font-size:14px}
.tp-mono{font-family:ui-monospace,Menlo,monospace;font-size:12.5px;color:#6b7480}
</style>
@endsection
@section('content')

<div class="card">
  <div class="card-header"><h4>拓扑</h4></div>
  <div class="card-body">
    {{-- `[!!]` 说清这一页与节点列表的分工。不说的话,人会以为它们只是两种画法。 --}}
    <p class="text-muted">
      这一页按<strong>路径</strong>看，回答的是「现在坏了的话，是哪一段」——
      以及<strong>用户实际拿得到哪几条路</strong>（与订阅里的条目一一对应）。
      「我们有几台机器」看<a href="/admin/nodes">节点列表</a>。
    </p>
    <div class="row">
      <div class="col-6 col-md-3"><div class="ad-minicard"><span><div class="n">{{ $stats['landings'] }}</div><div class="t">落地</div></span></div></div>
      <div class="col-6 col-md-3"><div class="ad-minicard"><span><div class="n">{{ $stats['paths'] }}</div><div class="t">可用路径</div></span></div></div>
      <div class="col-6 col-md-3"><div class="ad-minicard"><span><div class="n" style="color:{{ $stats['unreachable'] ? '#b4453c' : 'inherit' }}">{{ $stats['unreachable'] }}</div><div class="t">用户拿不到的落地</div></span></div></div>
      <div class="col-6 col-md-3"><div class="ad-minicard"><span><div class="n" style="color:{{ $stats['orphans'] ? '#9a6a12' : 'inherit' }}">{{ $stats['orphans'] }}</div><div class="t">闲置的中转</div></span></div></div>
    </div>
  </div>
</div>

@foreach($landings as $l)
  @php $n = $l['node']; $dest = $l['layers']['dest']; @endphp
  <div class="tp-land">
    <div class="tp-head">
      <span class="tp-name">#{{ $n->id }} {{ $n->name }}</span>
      <span class="tp-mono">{{ $n->server }}:{{ $n->port }}</span>
      @if(!$l['visible'])<span class="adm-pill danger">{{ $n->enabled ? '失联' : '已停用' }}</span>@endif
      @if($n->accept_proxy_protocol)<span class="adm-pill" title="该端口每个连接都必须带 PROXY 头，直连客户端会被全部拒绝">收 PROXY 头</span>@endif
      {{-- dest 那一层只有落地测得了 --}}
      @if($dest['state'] === 'bad')<span class="adm-pill danger" title="{{ $dest['detail'] }}">dest 不可达</span>
      @elseif($dest['state'] === 'warn')<span class="adm-pill warn" title="{{ $dest['detail'] }}">dest 变慢</span>
      @elseif($dest['state'] === 'ok')<span class="adm-pill ok" title="{{ $dest['detail'] }}">dest 正常</span>
      @elseif($dest['state'] === 'unknown')<span class="adm-pill" title="{{ $dest['detail'] }}">dest ?</span>@endif
    </div>

    @if($l['unreachable'])
      {{-- `[!!]` 这是最值得一眼看到的状态:节点列表上它照样显示"在线"。 --}}
      <div class="tp-none">
        <strong>用户拿不到这个落地</strong> —— 没有任何一条路径进得了订阅。
        @if($n->accept_proxy_protocol)
          它开了「收 PROXY 头」，所以不发直连条目；而目前没有任何在线的中转指向它。
        @else
          检查：节点是否启用并在线、是否有转发规则指向它。
        @endif
      </div>
    @else
      <div class="tp-paths">
        @foreach($l['paths'] as $p)
          <div class="tp-path" @if(!$p['in_sub']) style="opacity:.72" @endif>
            {{-- `[!!]` 订阅摘掉一条入口是【静默】的：用户只是少了个选择，没人会发现。
                 这里把"配置里有、订阅里没有"和原因一起说出来。 --}}
            @if(!$p['in_sub'])
              <span class="tp-seg warn" title="{{ $p['why_not'] }}">不在订阅里</span>
            @endif
            <span class="tp-seg">客户端</span><span class="tp-arrow">→</span>
            @if($p['kind'] === 'direct')
              <span class="tp-seg">直连 <span class="tp-mono">{{ $p['server'] }}:{{ $p['port'] }}</span></span>
            @else
              @php $hop = $p['hop']; $st = $hop['state']; @endphp
              <span class="tp-seg">
                中转 #{{ $p['relay']->id }} {{ $p['relay']->name }}
                <span class="tp-mono">{{ $p['server'] }}:{{ $p['port'] }}</span>
              </span>
              <span class="tp-arrow">→</span>
              {{-- 这一跳【只有中转自己测得了】：面板连不到 accept_proxy 落地 --}}
              <span class="tp-seg {{ $st === 'down' ? 'bad' : ($st === 'slow' ? 'warn' : '') }}"
                    title="这一跳只有中转自己测得了，面板测不了">
                @if($st === 'down') 到落地不通
                @elseif($st === 'slow') 到落地变慢 {{ $hop['delay_ms'] }}ms
                @elseif($st === 'unknown') 到落地 ?
                @else 到落地 {{ $hop['delay_ms'] ? $hop['delay_ms'].'ms' : '正常' }}
                @endif
              </span>
              @if($hop['proxy'] > 0)<span class="tp-seg">发 PROXY v{{ $hop['proxy'] }}</span>@endif
            @endif
            <span class="tp-arrow">→</span>
            <span class="tp-seg">落地 #{{ $n->id }}</span>
            @if($n->usesReality())
              <span class="tp-arrow">→</span>
              <span class="tp-seg {{ $dest['state'] === 'bad' ? 'bad' : ($dest['state'] === 'warn' ? 'warn' : '') }}">
                dest <span class="tp-mono">{{ $n->reality_dest ?: '未设置' }}</span>
              </span>
            @endif
            @if($p['kind'] === 'relay' && $p['hop']['rule'])
              <a href="/admin/rules/{{ $p['hop']['rule']->id }}/edit" class="tp-mono ml-auto">规则 #{{ $p['hop']['rule']->id }}</a>
            @endif
            @if(!$p['in_sub'])<div class="tp-mono" style="flex-basis:100%;color:#9a6a12">{{ $p['why_not'] }}</div>@endif
          </div>
        @endforeach
      </div>
    @endif
  </div>
@endforeach

@if($orphans)
  <div class="card">
    <div class="card-header"><h4>闲置的中转</h4></div>
    <div class="card-body">
      {{-- `[!]` 说清楚"为什么闲置",别只标一个状态 —— 否则人还得自己去四个地方翻。 --}}
      <p class="text-muted">这些中转没有承载任何用户路径。机器在跑，但没人经过它。</p>
      @foreach($orphans as $o)
        <div style="padding:10px 0;border-top:1px solid #eef0f4">
          <span class="tp-name">#{{ $o['node']->id }} {{ $o['node']->name }}</span>
          <span class="tp-mono">{{ $o['node']->server }}</span>
          <div class="text-muted" style="font-size:13px">{{ $o['why'] }}</div>
        </div>
      @endforeach
    </div>
  </div>
@endif

@if(!$landings)
  <div class="card"><div class="card-body"><div class="adm-empty">还没有落地节点</div></div></div>
@endif
@endsection
