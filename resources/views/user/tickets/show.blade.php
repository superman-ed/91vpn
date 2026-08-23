@extends('layouts.user')
@section('title', '工单详情')
@section('content')
<div class="card">
    <div class="card-header"><h4>{{ $ticket->subject }}</h4><div class="card-header-action">@if($ticket->status==='open')<span class="badge badge-primary">进行中</span>@else<span class="badge badge-secondary">已关闭</span>@endif</div></div>
    <div class="card-body">
        @foreach($ticket->replies as $r)
        <div class="p-3 mb-2" style="border-radius:8px;background:{{ $r->is_admin ? '#f3f6ff' : '#f7f7f7' }}">
            <div class="text-muted mb-1" style="font-size:12px">{{ $r->is_admin ? '👨‍💼 客服' : $r->user?->name }} · {{ $r->created_at?->format('Y-m-d H:i') }}</div>
            <div style="white-space:pre-wrap">{{ $r->content }}</div>
        </div>
        @endforeach
        @if($ticket->status==='open')
        <form method="POST" action="/user/ticket/{{ $ticket->id }}/reply" class="mt-3">@csrf
            <div class="form-group"><textarea name="content" rows="3" class="form-control" placeholder="回复..." required></textarea></div>
            <button class="btn btn-primary">回复</button>
        </form>
        @else<p class="text-muted mt-3">工单已关闭</p>@endif
    </div>
</div>
@endsection
