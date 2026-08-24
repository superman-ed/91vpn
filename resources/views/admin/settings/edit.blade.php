@extends('layouts.admin')
@section('title', '站点设置')
@section('content')
<div class="card"><div class="card-body">
<form method="POST" action="/admin/settings">@csrf @method('PUT')
    <div class="form-group">
        <label>购买须知（每行一条，显示在收银台底部）</label>
        <textarea name="buy_notice" rows="6" class="form-control">{{ old('buy_notice', $buyNotice) }}</textarea>
        <small class="text-muted">留空则恢复内置默认文案。</small>
    </div>
    <button class="btn btn-primary">保存</button>
</form>
</div></div>
@endsection
