@extends('layouts.user')
@section('title', '工单支持')
@section('head')
<style>
.tk-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
.tk-bar h4 { font-size: 18px; font-weight: 700; color: #34395e; margin: 0; }
.tk-new { border-radius: 9px; font-weight: 700; background: linear-gradient(135deg,#6777ef,#5a67e8); border: none; color: #fff; }
.tk-new:hover { filter: brightness(1.05); color: #fff; }
.tk-item {
    display: flex; align-items: center; gap: 14px; background: #fff; border: none; border-radius: 13px;
    padding: 16px 20px; margin-bottom: 12px; box-shadow: 0 4px 14px rgba(103,119,239,.07);
    transition: transform .15s, box-shadow .15s; text-decoration: none;
}
.tk-item:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(103,119,239,.14); text-decoration: none; }
.tk-ic { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; flex-shrink: 0; }
.tk-ic.open { background: linear-gradient(135deg,#6777ef,#5a67e8); }
.tk-ic.closed { background: #cfd6df; }
.tk-body { flex: 1; min-width: 0; }
.tk-subj { font-size: 15px; font-weight: 700; color: #34395e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tk-time { font-size: 12.5px; color: #98a6ad; margin-top: 2px; }
.tk-status { font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 20px; white-space: nowrap; }
.tk-status.open { background: #e7f3ff; color: #3a8ee6; }
.tk-status.closed { background: #f2f3f5; color: #98a6ad; }
.tk-empty { text-align: center; color: #98a6ad; padding: 60px 0; }
</style>
@endsection
@section('content')
<div class="tk-bar">
    <h4><i class="fas fa-headset text-primary"></i> 我的工单</h4>
    <a href="/user/ticket/create" class="btn tk-new"><i class="fas fa-plus"></i> 新建工单</a>
</div>

@forelse($tickets as $t)
<a href="/user/ticket/{{ $t->id }}" class="tk-item">
    <span class="tk-ic {{ $t->status === 'open' ? 'open' : 'closed' }}"><i class="fas fa-{{ $t->status === 'open' ? 'comment-dots' : 'check' }}"></i></span>
    <span class="tk-body">
        <div class="tk-subj">{{ $t->subject }}</div>
        <div class="tk-time"><i class="fas fa-clock"></i> 最后更新 {{ $t->updated_at?->format('Y-m-d H:i') }}</div>
    </span>
    <span class="tk-status {{ $t->status === 'open' ? 'open' : 'closed' }}">{{ $t->status === 'open' ? '进行中' : '已关闭' }}</span>
    <i class="fas fa-chevron-right text-muted"></i>
</a>
@empty
<div class="card" style="border:none;border-radius:14px"><div class="tk-empty"><i class="fas fa-headset fa-3x mb-3 d-block"></i>还没有工单<br><small>遇到问题？点右上角「新建工单」联系客服</small></div></div>
@endforelse
@endsection
