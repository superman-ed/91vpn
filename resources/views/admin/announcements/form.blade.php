@extends('layouts.admin')
@section('title', $item->exists ? '编辑公告' : '发布公告')
@section('content')
<div class="card"><div class="card-body">
<form method="POST" action="{{ $item->exists ? '/admin/announcements/'.$item->id : '/admin/announcements' }}">@csrf @if($item->exists)@method('PUT')@endif
<div class="form-group"><label>标题</label><input name="title" value="{{ old('title',$item->title) }}" class="form-control" required></div>
<div class="form-group"><label>内容</label><textarea name="content" rows="6" class="form-control" required>{{ old('content',$item->content) }}</textarea></div>
<div class="row">
<div class="form-group col-md-6"><label>发布</label><select name="published" class="form-control"><option value="1" @selected(old('published',$item->published ?? 1))>发布</option><option value="0">草稿</option></select></div>
<div class="form-group col-md-6"><label>排序</label><input name="sort" type="number" value="{{ old('sort',$item->sort ?? 0) }}" class="form-control"></div>
</div>
<button class="btn btn-primary">保存</button> <a href="/admin/announcements" class="btn btn-light">取消</a>
</form></div></div>
@endsection
