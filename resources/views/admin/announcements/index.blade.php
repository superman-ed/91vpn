@extends('layouts.admin')
@section('title', '公告管理')
@section('content')
<div class="card"><div class="card-header"><h4>公告列表</h4><div class="card-header-action"><a href="/admin/announcements/create" class="btn btn-primary btn-sm">发布公告</a></div></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>ID</th><th>标题</th><th>状态</th><th>时间</th><th>操作</th></tr></thead><tbody>
@forelse($items as $a)
<tr><td>{{ $a->id }}</td><td>{{ $a->title }}</td><td>@if($a->published)<span class="badge badge-success">已发布</span>@else<span class="badge badge-secondary">草稿</span>@endif</td>
<td>{{ $a->created_at?->format('Y-m-d H:i') }}</td>
<td><a href="/admin/announcements/{{ $a->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
<form method="POST" action="/admin/announcements/{{ $a->id }}" class="d-inline" onsubmit="return confirm('确认删除？')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form></td></tr>
@empty<tr><td colspan="5" class="text-muted">暂无公告</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
