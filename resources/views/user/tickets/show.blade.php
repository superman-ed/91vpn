@extends('layouts.user')
@section('title', '工单详情')
@section('content')
<div class="panel"><h3>{{ $ticket->subject }} <span style="font-size:13px;color:#6c757d">{{ $ticket->status === 'open' ? '进行中' : '已关闭' }}</span></h3>
@foreach($ticket->replies as $r)
<div style="margin:14px 0;padding:12px 16px;border-radius:8px;background:{{ $r->is_admin ? '#f9fafe' : '#f7f7f7' }}">
<div style="font-size:12px;color:#6c757d;margin-bottom:6px">{{ $r->is_admin ? '👨‍💼 客服' : $r->user?->name }} · {{ $r->created_at?->format('Y-m-d H:i') }}</div>
<div style="white-space:pre-wrap">{{ $r->content }}</div>
</div>
@endforeach
@if($ticket->status === 'open')
<form method="POST" action="/user/ticket/{{ $ticket->id }}/reply" style="margin-top:16px">@csrf
<textarea name="content" rows="3" style="width:100%" placeholder="回复..." required></textarea>
<div style="margin-top:10px"><button class="btn">回复</button></div>
</form>
@else<p style="color:#acb5c9;margin-top:16px">工单已关闭</p>@endif
</div>
@endsection
