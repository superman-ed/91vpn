@extends('layouts.admin')
@section('title', '工单处理')
@section('content')
<div class="panel"><h3>{{ $ticket->subject }} <span style="font-size:13px;color:#6c757d">来自 {{ $ticket->user?->email }} · {{ $ticket->status === 'open' ? '进行中' : '已关闭' }}</span></h3>
@foreach($ticket->replies as $r)
<div style="margin:14px 0;padding:12px 16px;border-radius:8px;background:{{ $r->is_admin ? '#eef7ff' : '#f7f7f7' }}">
<div style="font-size:12px;color:#6c757d;margin-bottom:6px">{{ $r->is_admin ? '👨‍💼 客服' : $r->user?->email }} · {{ $r->created_at?->format('Y-m-d H:i') }}</div>
<div style="white-space:pre-wrap">{{ $r->content }}</div>
</div>
@endforeach
@if($ticket->status === 'open')
<form method="POST" action="/admin/tickets/{{ $ticket->id }}/reply" style="margin-top:16px">@csrf
<textarea name="content" rows="3" style="width:100%" placeholder="回复用户..." required></textarea>
<div style="margin-top:10px"><button class="btn">回复</button>
<button formaction="/admin/tickets/{{ $ticket->id }}/close" class="btn danger">关闭工单</button></div>
</form>
@else<p style="color:#acb5c9;margin-top:16px">工单已关闭</p>@endif
</div>
@endsection
