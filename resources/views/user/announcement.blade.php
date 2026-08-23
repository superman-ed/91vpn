@extends('layouts.user')
@section('title', '公告')
@section('content')
@forelse ($announcements as $a)
<div class="card">
    <div class="card-header"><h4>{{ $a->title }}</h4></div>
    <div class="card-body">
        <div style="line-height:1.8">{!! nl2br(e($a->content)) !!}</div>
        <div class="text-muted mt-3" style="font-size:12px">{{ $a->created_at?->format('Y-m-d H:i') }}</div>
    </div>
</div>
@empty<div class="card"><div class="card-body text-muted">暂无公告</div></div>@endforelse
@endsection
