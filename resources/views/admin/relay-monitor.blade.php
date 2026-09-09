@extends('layouts.admin')
@section('title', '节点监控')
@section('content')
@php
  use App\Support\Fmt;
@endphp

<div class="adm-head">
  <h4>节点监控</h4>
</div>

{{-- `[!!]` 这一页混着两种时间尺度，必须分清楚 ——
     否则运维会拿"按天累计"去判断"现在通不通"。 --}}
<div class="info-note mb-4">
  <strong>上半部分是实时快照</strong>（上游存活、活跃连接、在线来源），
  跟着节点的上报周期走；<strong>下半部分是按天累计</strong>。两者回答的不是同一个问题：
  快照答"现在通不通"，累计答"用了多少"。
  <br>
  快照都标了上报时间。超过 <strong>{{ $statusStale }} 分钟</strong>没更新的会标成
  <span class="adm-pill warn">数据陈旧</span> —— 那时"显示存活"不代表现在还活着。
</div>

<div class="adm-head" style="margin-bottom:10px">
  <h4 style="font-size:15px">上游状态（实时）</h4>
</div>

@forelse ($status as $key => $outs)
  @php
    [$rid, $nid] = explode('-', $key);
    $first = $outs->first();
    $stale = $first->stale();
    $src = $sources[$key] ?? null;
  @endphp
  <div class="card adm-panel mb-3">
    <div class="card-header px-4 py-3 border-0">
      <h4 class="mb-0" style="font-size:15px;font-weight:700;color:#34395e">
        {{ $ruleNames[$rid] ?? "规则 #$rid（已删除）" }}
        <span class="hint" style="font-weight:400">@ {{ $nodeNames[$nid] ?? "#$nid" }}</span>
        @if ($src)
          <span class="adm-pill info ml-2">{{ $src->n }} 个在线来源</span>
        @endif
        @if ($stale)
          <span class="adm-pill warn ml-2">数据陈旧</span>
        @endif
        <span class="hint ml-2" style="font-weight:400">
          {{ $first->reported_at->diffForHumans() }}上报
        </span>
      </h4>
    </div>
    <div class="card-body">
      <table class="table adm-table">
        <thead><tr><th>上游</th><th>拨号目标</th><th>池</th><th>状态</th><th>活跃连接</th></tr></thead>
        <tbody>
        @foreach ($outs as $o)
          <tr>
            <td><code class="hint">{{ $o->tag }}</code></td>
            <td><code>{{ $o->dial ?: '—' }}</code></td>
            <td>
              @if ($o->backup)<span class="adm-pill muted">备池</span>
              @else<span class="adm-pill primary">主池</span>@endif
            </td>
            <td>
              @if ($stale)
                <span class="adm-pill muted">未知</span>
              @elseif ($o->alive)
                <span class="adm-pill ok">存活</span>
              @else
                <span class="adm-pill danger">判死</span>
              @endif
            </td>
            <td>{{ $o->live }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
      @php $deadMain = $outs->where('backup', false)->where('alive', false)->count(); @endphp
      @if (! $stale && $deadMain > 0 && $deadMain === $outs->where('backup', false)->count())
        <div class="danger-note m-3">
          <strong>主池全部判死。</strong>
          若备池也没有存活上游，这条规则现在【不通】——
          agent 不会退化成直连（那会让本该走中转的流量泄漏出去），而是让连接失败。
        </div>
      @endif
    </div>
  </div>
@empty
  <div class="card adm-panel mb-3">
    <div class="card-body">
      <div class="adm-empty">
        还没有上游状态。<span class="hint">中转节点上报后出现；落地节点没有转发规则，不会有这一项。</span>
      </div>
    </div>
  </div>
@endforelse

<div class="adm-head" style="margin:22px 0 10px">
  <h4 style="font-size:15px">整机流量（按天累计）</h4>
</div>

<div class="card adm-panel">
  <div class="card-body">
    <table class="table adm-table">
      <thead>
        <tr>
          <th>#</th><th>节点</th><th>角色</th><th>心跳</th>
          @foreach ($days as $d)
            <th class="text-right">{{ substr($d, 5) }}</th>
          @endforeach
          <th class="text-right">7 日合计</th>
        </tr>
      </thead>
      <tbody>
      @forelse ($nodes as $n)
        @php
          $age = $now - (int) $n->last_heartbeat;
          $row = $traffic[$n->id] ?? [];
          $sum = array_sum($row);
        @endphp
        <tr>
          <td>{{ $n->id }}</td>
          <td>
            {{ $n->name }}
            <div class="hint">{{ $n->server }}:{{ $n->port }}</div>
          </td>
          <td><span class="adm-pill {{ $n->forwards() ? 'primary' : 'muted' }}">{{ $n->role }}</span></td>
          <td>
            @if (! $n->enabled)
              <span class="adm-pill muted">停用</span>
            @elseif (! $n->last_heartbeat)
              <span class="adm-pill muted">从未</span>
            @elseif ($age > $staleSec)
              <span class="adm-pill danger">失联 {{ intdiv($age, 60) }} 分</span>
            @else
              <span class="adm-pill ok">{{ $age }}s 前</span>
            @endif
          </td>
          @foreach ($days as $d)
            <td class="text-right">
              @if (($row[$d] ?? 0) > 0)
                {{ Fmt::bytes($row[$d], 1) }}
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
          @endforeach
          <td class="text-right"><strong>{{ $sum ? Fmt::bytes($sum, 2) : '—' }}</strong></td>
        </tr>
      @empty
        <tr><td colspan="{{ 5 + $days->count() }}" class="adm-empty">还没有节点。</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
