@extends('layouts.admin')
@section('title', '客户端下载')
@section('content')
<div class="card">
  <div class="card-header">
    <h4>客户端下载</h4>
    <div class="card-header-action"><a href="/admin/downloads/create" class="btn btn-primary btn-sm">新增</a></div>
  </div>
  <div class="card-body">
    {{-- `[!]` 说清楚"链接为空"不是错误状态 —— 否则人会以为这里坏了。 --}}
    <p class="text-muted">用户在「客户端下载」页看到的就是这张表。
      <strong>链接留空 = 显示为「即将推出」</strong>，那一栏仍然会列出来。</p>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>排序</th><th>平台</th><th>显示名</th><th>链接</th><th>版本</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        @forelse($items as $i)
          <tr>
            <td class="text-muted">{{ $i->sort }}</td>
            <td><i class="{{ $i->icon }}"></i> {{ $i->platform }}</td>
            <td>{{ $i->label }}</td>
            <td style="max-width:320px;word-break:break-all">
              @if($i->url)<a href="{{ $i->url }}" target="_blank" rel="noopener">{{ $i->url }}</a>
              @else<span class="adm-pill">即将推出</span>@endif
            </td>
            <td>{{ $i->version ?: '—' }}</td>
            <td>@if($i->enabled)<span class="adm-pill ok">启用</span>@else<span class="adm-pill">停用</span>@endif</td>
            <td>
              <a href="/admin/downloads/{{ $i->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
              <form method="POST" action="/admin/downloads/{{ $i->id }}" class="d-inline" data-dgr="删除后用户页上就没有这一栏了。">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">删除</button></form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7"><div class="adm-empty">还没有配置任何客户端</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
