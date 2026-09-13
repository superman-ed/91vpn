@extends('layouts.admin')
@section('title', '首页 Banner')
@section('content')
<div class="card">
  <div class="card-header">
    <h4>首页 Banner</h4>
    <div class="card-header-action"><a href="/admin/banners/create" class="btn btn-primary btn-sm">新增</a></div>
  </div>
  <div class="card-body">
    <p class="text-muted">按排序显示在用户首页顶部。没填图片就显示成一条文字通知。</p>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>排序</th><th>标题</th><th>展示时段</th><th>当前状态</th><th>操作</th></tr></thead>
        <tbody>
        @forelse($items as $i)
          @php $why = $i->whyHidden(); @endphp
          <tr>
            <td class="text-muted">{{ $i->sort }}</td>
            <td>
              @if($i->image_url)<img src="{{ $i->image_url }}" alt="" style="height:28px;vertical-align:middle;margin-right:8px;border-radius:3px">@endif
              {{ $i->title }}
              @if($i->link)<a href="{{ $i->link }}" target="_blank" rel="noopener" class="text-muted" style="font-size:12px">↗</a>@endif
            </td>
            <td class="text-muted" style="font-size:13px">
              {{ $i->starts_at?->format('m-d H:i') ?: '不限' }} ~ {{ $i->ends_at?->format('m-d H:i') ?: '不限' }}
            </td>
            {{-- `[!!]` 不显示时要说【为什么】。只标一个"未展示"的话，
                 人会去检查图片、链接、缓存，而真因往往只是时间写反了。 --}}
            <td>
              @if($why)<span class="adm-pill warn" title="{{ $why }}">未展示</span>
                <div class="hint">{{ $why }}</div>
              @else<span class="adm-pill ok">展示中</span>@endif
            </td>
            <td>
              <a href="/admin/banners/{{ $i->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
              <form method="POST" action="/admin/banners/{{ $i->id }}" class="d-inline" data-dgr="删除后首页上就没有这一条了。">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">删除</button></form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5"><div class="adm-empty">还没有 Banner</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
