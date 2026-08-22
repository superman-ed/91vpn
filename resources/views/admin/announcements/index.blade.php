@extends('layouts.admin')
@section('title', '公告管理')
@section('content')
<div class="panel"><h3>公告列表 <a href="/admin/announcements/create" class="btn sm">+ 发布公告</a></h3>
<table>
<tr><th>ID</th><th>标题</th><th>已发布</th><th>时间</th><th>操作</th></tr>
@forelse($items as $a)
<tr><td>{{ $a->id }}</td><td>{{ $a->title }}</td><td>{{ $a->published ? '✅' : '草稿' }}</td>
<td>{{ $a->created_at?->format('Y-m-d H:i') }}</td>
<td><a href="/admin/announcements/{{ $a->id }}/edit" class="btn ghost sm">编辑</a>
<form method="POST" action="/admin/announcements/{{ $a->id }}" style="display:inline" onsubmit="return confirm('确认删除？')">@csrf @method('DELETE')<button class="btn danger sm">删除</button></form></td></tr>
@empty<tr><td colspan="5" style="color:#acb5c9">暂无公告</td></tr>@endforelse
</table></div>
@endsection
