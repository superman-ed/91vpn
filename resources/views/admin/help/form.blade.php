@extends('layouts.admin')
@section('title', $item->exists ? '编辑文档' : '新增文档')
@section('content')
<div class="card"><div class="card-body">
<form method="POST" action="{{ $item->exists ? '/admin/help/'.$item->id : '/admin/help' }}">@csrf @if($item->exists)@method('PUT')@endif
<div class="row">
<div class="form-group col-md-6"><label>分类</label><input name="category" value="{{ old('category',$item->category) }}" class="form-control" list="help-cats" required><datalist id="help-cats"><option value="常见问题"><option value="连接使用"><option value="账号"><option value="套餐支付"></datalist><small class="text-muted">同分类会在 App 里归为一组;可复用已有分类或新建</small></div>
<div class="form-group col-md-3"><label>平台</label><select name="platform" class="form-control">@foreach(['all'=>'全部通用','windows'=>'Windows','android'=>'安卓','ios'=>'iOS','macos'=>'macOS'] as $v=>$l)<option value="{{ $v }}" @selected(old('platform',$item->platform ?? 'all')===$v)>{{ $l }}</option>@endforeach</select><small class="text-muted">仅对应平台客户端可见;全部通用=各端都显示</small></div>
<div class="form-group col-md-3"><label>排序</label><input name="sort" type="number" value="{{ old('sort',$item->sort ?? 0) }}" class="form-control"><small class="text-muted">越大越靠前</small></div>
</div>
<div class="form-group"><label>标题</label><input name="title" value="{{ old('title',$item->title) }}" class="form-control" required></div>
<div class="form-group"><label>正文</label><textarea name="content" rows="10" class="form-control" required placeholder="纯文本;段落之间空一行">{{ old('content',$item->content) }}</textarea></div>
<div class="form-group"><label>状态</label><select name="published" class="form-control"><option value="1" @selected(old('published',$item->published ?? 1))>发布</option><option value="0" @selected(!old('published',$item->published ?? 1))>草稿</option></select></div>
<button class="btn btn-primary">保存</button> <a href="/admin/help" class="btn btn-light">取消</a>
</form></div></div>
@endsection
