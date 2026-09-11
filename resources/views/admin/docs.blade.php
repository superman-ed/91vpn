@extends('layouts.admin')
@section('title', '技术文档')
@section('content')

<style>
  .doc-wrap{display:flex;gap:20px;align-items:flex-start}
  .doc-nav{flex:0 0 250px;position:sticky;top:16px}
  .doc-nav a{display:block;padding:7px 10px;border-radius:6px;color:#34395e;
             font-size:13px;line-height:1.4;text-decoration:none}
  .doc-nav a:hover{background:#f4f6f9}
  .doc-nav a.on{background:#6777ef;color:#fff;font-weight:600}
  .doc-body{flex:1;min-width:0}
  /* `[!]` 文档里有宽表格和代码块 —— 不给它们自己的横向滚动，整页会被撑横。 */
  .doc-body table{width:100%;margin:14px 0;font-size:13px;border-collapse:collapse}
  .doc-body table th,.doc-body table td{border:1px solid #e9ecf3;padding:7px 10px;vertical-align:top}
  .doc-body table th{background:#f4f6f9;font-weight:600;white-space:nowrap}
  .doc-body pre{background:#2f3640;color:#f1f2f6;padding:13px 15px;border-radius:8px;
                overflow-x:auto;font-size:12.5px;line-height:1.55}
  .doc-body code{background:#f1f3f9;padding:2px 5px;border-radius:4px;font-size:12.5px;color:#e83e8c}
  .doc-body pre code{background:none;padding:0;color:inherit}
  .doc-body h1{font-size:23px;margin:0 0 16px;padding-bottom:10px;border-bottom:2px solid #eef0f5}
  .doc-body h2{font-size:18px;margin:26px 0 12px;color:#34395e}
  .doc-body h3{font-size:15px;margin:20px 0 9px;color:#6c757d}
  .doc-body blockquote{border-left:3px solid #6777ef;margin:12px 0;padding:6px 14px;
                       background:#f8f9fc;color:#5a5c69}
  .doc-body li{margin:4px 0}
  .doc-body .tbl{overflow-x:auto}
</style>

<div class="section-header"><h1>技术文档</h1></div>

@if ($files->isEmpty())
  <div class="card"><div class="card-body">
    <div class="adm-empty">
      <i class="fas fa-book fa-2x mb-2 d-block"></i>
      <p>还没有同步文档。</p>
      <p class="text-muted" style="font-size:13px">
        在宿主机上执行：<code>bash deploy/sync-docs.sh</code><br>
        文档源在 <code>sogacore/docs/guide/</code>，这里只是副本。
      </p>
    </div>
  </div></div>
@else
<div class="doc-wrap">
  <div class="doc-nav">
    <div class="card"><div class="card-body" style="padding:10px">
      @foreach ($files as $f)
        <a href="/admin/docs/{{ $f }}" class="{{ $f === $current ? 'on' : '' }}">{{ $titles[$f] ?? $f }}</a>
      @endforeach
    </div></div>

    {{-- `[!!]` 副本必然会过期。显示来源提交与同步时间，让过期【看得见】——
         静默过期的文档比没有文档更坏，因为人会照着它做。 --}}
    @if ($source)
      <div class="text-muted" style="font-size:11.5px;padding:10px 12px;line-height:1.7">
        同步自 <code>{{ $source['commit'] ?? '?' }}</code><br>
        {{ $source['synced_at'] ?? '' }}<br>
        <span title="改文档请改 sogacore/docs/guide，然后重新同步">源：sogacore/docs/guide</span>
      </div>
    @endif
  </div>

  <div class="doc-body">
    <div class="card"><div class="card-body">
      {!! $html !!}
    </div></div>
  </div>
</div>

<script>
// `[!]` markdown 生成的宽表格会把整页撑横。给每个表包一层可横向滚动的容器，
// 而不是把表压窄 —— 压窄会让本来就长的字段说明挤成一团。
document.querySelectorAll('.doc-body table').forEach(function (t) {
  if (t.parentElement.classList.contains('tbl')) return;
  var w = document.createElement('div'); w.className = 'tbl';
  t.parentNode.insertBefore(w, t); w.appendChild(t);
});
</script>
@endif
@endsection
