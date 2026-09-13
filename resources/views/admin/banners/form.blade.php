@extends('layouts.admin')
@section('title', $item->exists ? '编辑 Banner' : '新增 Banner')
@section('content')
<form method="POST" action="{{ $item->exists ? '/admin/banners/'.$item->id : '/admin/banners' }}">
  @csrf @if($item->exists) @method('PUT') @endif
  <div class="card">
    <div class="card-header"><h4>{{ $item->exists ? '编辑' : '新增' }} Banner</h4></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group col-md-10">
          <label>标题 <span class="text-danger">*</span></label>
          <input name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $item->title) }}" required>
          @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-group col-md-2">
          <label>排序</label>
          <input name="sort" type="number" class="form-control" value="{{ old('sort', $item->sort ?? 0) }}">
        </div>
      </div>
      <div class="form-group">
        <label>图片地址</label>
        <input name="image_url" class="form-control @error('image_url') is-invalid @enderror" value="{{ old('image_url', $item->image_url) }}" placeholder="https://…">
        {{-- `[!]` 不做上传:上传意味着存储、权限、清理、体积限制和一条新的对外面,
             而当前需求只是"换一张图"。 --}}
        <div class="hint">这里只填地址，<strong>不支持上传</strong> —— 图放到图床或对象存储再贴地址。留空则显示成文字条。</div>
        @error('image_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="form-group">
        <label>文字（没有图片时显示）</label>
        <input name="text" class="form-control" value="{{ old('text', $item->text) }}">
      </div>
      <div class="form-group">
        <label>跳转链接</label>
        <input name="link" class="form-control @error('link') is-invalid @enderror" value="{{ old('link', $item->link) }}" placeholder="https://…">
        @error('link')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>开始展示</label>
          <input name="starts_at" type="datetime-local" class="form-control" value="{{ old('starts_at', $item->starts_at?->format('Y-m-d\TH:i')) }}">
          <div class="hint">留空 = 立即开始</div>
        </div>
        <div class="form-group col-md-6">
          <label>结束展示</label>
          <input name="ends_at" type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', $item->ends_at?->format('Y-m-d\TH:i')) }}">
          {{-- `[!!]` 活动 Banner 一定要填结束时间。"忘了下架"没有任何现象提醒。 --}}
          <div class="hint">留空 = 一直展示。<strong>活动类的一定要填</strong> —— 忘了下架是不会有任何提示的。</div>
          @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
      <label class="custom-switch">
        <input type="checkbox" name="enabled" value="1" class="custom-switch-input" {{ old('enabled', $item->enabled ?? true) ? 'checked' : '' }}>
        <span class="custom-switch-indicator"></span><span class="custom-switch-description">启用</span>
      </label>
    </div>
    <div class="card-footer text-right">
      <a href="/admin/banners" class="btn btn-secondary">返回</a>
      <button class="btn btn-primary">保存</button>
    </div>
  </div>
</form>
@endsection
