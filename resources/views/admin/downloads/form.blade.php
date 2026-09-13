@extends('layouts.admin')
@section('title', $item->exists ? '编辑客户端下载' : '新增客户端下载')
@section('content')
<form method="POST" action="{{ $item->exists ? '/admin/downloads/'.$item->id : '/admin/downloads' }}">
  @csrf @if($item->exists) @method('PUT') @endif
  <div class="card">
    <div class="card-header"><h4>{{ $item->exists ? '编辑' : '新增' }}客户端下载</h4></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group col-md-3">
          <label>平台 <span class="text-danger">*</span></label>
          <input name="platform" class="form-control @error('platform') is-invalid @enderror" value="{{ old('platform', $item->platform) }}" required placeholder="Windows">
          @error('platform')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-group col-md-5">
          <label>显示名 <span class="text-danger">*</span></label>
          <input name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label', $item->label) }}" required placeholder="91VPN For Windows">
          @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-group col-md-2">
          <label>图标</label>
          <input name="icon" class="form-control" value="{{ old('icon', $item->icon) }}" placeholder="fab fa-windows">
          <div class="hint">Font Awesome 类名</div>
        </div>
        <div class="form-group col-md-2">
          <label>排序</label>
          <input name="sort" type="number" class="form-control" value="{{ old('sort', $item->sort ?? 0) }}">
          <div class="hint">小的在前</div>
        </div>
      </div>
      <div class="form-group">
        <label>下载链接</label>
        <input name="url" class="form-control @error('url') is-invalid @enderror" value="{{ old('url', $item->url) }}" placeholder="https://…">
        {{-- `[!!]` 留空不是"没填完",是一个有意义的状态。 --}}
        <div class="hint"><strong>留空 = 用户页显示「即将推出」</strong>，那一栏仍会列出来。必须是 http(s) 开头的完整地址。</div>
        @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="form-row">
        <div class="form-group col-md-3">
          <label>版本号</label>
          <input name="version" class="form-control" value="{{ old('version', $item->version) }}" placeholder="1.2.0">
        </div>
        <div class="form-group col-md-9">
          <label>备注</label>
          <input name="note" class="form-control" value="{{ old('note', $item->note) }}" placeholder="例如：需要 Windows 10 以上">
        </div>
      </div>
      <label class="custom-switch">
        <input type="checkbox" name="enabled" value="1" class="custom-switch-input" {{ old('enabled', $item->enabled ?? true) ? 'checked' : '' }}>
        <span class="custom-switch-indicator"></span><span class="custom-switch-description">启用（停用后用户页不显示这一栏）</span>
      </label>
    </div>
    <div class="card-footer text-right">
      <a href="/admin/downloads" class="btn btn-secondary">返回</a>
      <button class="btn btn-primary">保存</button>
    </div>
  </div>
</form>
@endsection
