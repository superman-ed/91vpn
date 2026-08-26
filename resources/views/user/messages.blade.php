@extends('layouts.user')
@section('title', '我的消息')
@section('content')
@php $typeMeta = ['system' => ['系统', '#6777ef'], 'expiry' => ['到期提醒', '#e6912a'], 'marketing' => ['活动', '#7c4ddb'], 'notice' => ['通知', '#3aa0c7']]; @endphp
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
    <h4 style="font-size:20px;font-weight:700;color:#34395e;margin:0"><i class="fas fa-bell text-primary"></i> 我的消息 @if($unread > 0)<span style="font-size:13px;color:#fc544b;font-weight:600">（{{ $unread }} 条未读）</span>@endif</h4>
    @if($unread > 0)
    <form method="POST" action="/user/messages/read-all">@csrf<button class="btn btn-sm" style="border-radius:9px;background:#eef0ff;color:#6777ef;border:none;font-weight:600"><i class="fas fa-check-double"></i> 全部已读</button></form>
    @endif
</div>

@if(session('status'))<div class="alert alert-success" style="border-radius:12px">{{ session('status') }}</div>@endif

<div style="display:flex;flex-direction:column;gap:12px">
    @forelse($notifications as $n)
    @php [$tName, $tColor] = $typeMeta[$n->type] ?? ['消息', '#98a6ad']; $isUnread = ! $n->read_at; @endphp
    <div style="background:#fff;border-radius:14px;padding:18px 20px;border:1px solid {{ $isUnread ? '#dfe4ff' : '#f0f1f5' }};box-shadow:0 3px 12px rgba(103,119,239,.05);position:relative">
        @if($isUnread)<span style="position:absolute;left:8px;top:22px;width:8px;height:8px;border-radius:50%;background:#fc544b"></span>@endif
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:11px;font-weight:700;color:#fff;background:{{ $tColor }};padding:2px 9px;border-radius:20px">{{ $tName }}</span>
                <b style="font-size:15px;color:#34395e">{{ $n->title }}</b>
            </div>
            <span style="font-size:12px;color:#98a6ad">{{ $n->created_at?->diffForHumans() }}</span>
        </div>
        <div style="font-size:13.5px;color:#54667a;line-height:1.7;white-space:pre-line">{{ $n->content }}</div>
        @if($isUnread)
        <form method="POST" action="/user/messages/{{ $n->id }}/read" style="margin-top:10px">@csrf<button class="btn btn-sm" style="border-radius:8px;background:#f4f6fb;color:#7a869a;border:none;font-size:12.5px">标记已读</button></form>
        @endif
    </div>
    @empty
    <div style="background:#fff;border-radius:14px;padding:56px 20px;text-align:center;color:#98a6ad">
        <i class="fas fa-bell-slash fa-3x mb-3 d-block" style="opacity:.35"></i>暂无消息
    </div>
    @endforelse
</div>

@if($notifications->hasPages())<div style="margin-top:18px">{{ $notifications->links('pagination::bootstrap-4') }}</div>@endif
@endsection
