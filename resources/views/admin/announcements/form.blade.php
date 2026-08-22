@extends('layouts.admin')
@section('title', $item->exists ? '编辑公告' : '发布公告')
@section('content')
<div class="panel"><form method="POST" action="{{ $item->exists ? '/admin/announcements/'.$item->id : '/admin/announcements' }}">
@csrf @if($item->exists)@method('PUT')@endif
<label>标题</label><input name="title" value="{{ old('title',$item->title) }}" style="width:100%" required>
<label>内容</label><textarea name="content" rows="6" style="width:100%" required>{{ old('content',$item->content) }}</textarea>
<div class="grid2">
<div><label>发布</label><select name="published" style="width:100%"><option value="1" @selected(old('published',$item->published ?? 1))>发布</option><option value="0">草稿</option></select></div>
<div><label>排序</label><input name="sort" type="number" value="{{ old('sort',$item->sort ?? 0) }}" style="width:100%"></div>
</div>
<div style="margin-top:20px"><button class="btn">保存</button> <a href="/admin/announcements" class="btn ghost">取消</a></div>
</form></div>
@endsection
