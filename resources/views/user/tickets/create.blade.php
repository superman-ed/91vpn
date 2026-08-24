@extends('layouts.user')
@section('title', '新建工单')
@section('head')
<style>
.tkc-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
.tkc-head .back { color: #7a869a; font-size: 14px; text-decoration: none; }
.tkc-head .back:hover { color: #6777ef; }
.tkc-head h4 { font-size: 18px; font-weight: 700; color: #34395e; margin: 0; }
.tkc-card { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); }
.tkc-card .card-body { padding: 24px; }
.tkc-card label { font-size: 13px; color: #7a869a; font-weight: 600; }
.tkc-card .form-control { border-radius: 10px; border-color: #eef0f5; }
.tkc-tip { background: #eef1ff; border-radius: 11px; padding: 12px 16px; font-size: 13px; color: #54667a; margin-bottom: 18px; }
.tkc-tip i { color: #6777ef; }
.tkc-btn { border-radius: 9px; font-weight: 700; background: linear-gradient(135deg,#6777ef,#5a67e8); border: none; color: #fff; }
.tkc-btn:hover { filter: brightness(1.05); color: #fff; }
</style>
@endsection
@section('content')
<div class="tkc-head">
    <a href="/user/ticket" class="back"><i class="fas fa-arrow-left"></i> 返回</a>
    <h4><i class="fas fa-pen text-primary"></i> 新建工单</h4>
</div>
<div class="card tkc-card">
    <div class="card-body">
        <div class="tkc-tip"><i class="fas fa-lightbulb"></i> 请尽量详细描述问题(如：使用的客户端、节点、报错信息、发生时间),有助于客服快速定位。</div>
        <form method="POST" action="/user/ticket">@csrf
            <div class="form-group">
                <label>标题</label>
                <input name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" placeholder="一句话概括你的问题" required>
                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label>问题描述</label>
                <textarea name="content" rows="7" class="form-control @error('content') is-invalid @enderror" placeholder="详细描述你遇到的问题…" required>{{ old('content') }}</textarea>
                @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button class="btn tkc-btn"><i class="fas fa-paper-plane"></i> 提交工单</button>
            <a href="/user/ticket" class="btn btn-light" style="border-radius:9px">取消</a>
        </form>
    </div>
</div>
@endsection
