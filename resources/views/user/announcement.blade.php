@extends('layouts.user')
@section('title', '公告')
@section('content')
@forelse ($announcements as $a)
    <div class="panel">
        <h3>{{ $a->title }}</h3>
        <div style="font-size:14px;color:#6c757d;line-height:1.7">{!! nl2br(e($a->content)) !!}</div>
        <div style="font-size:12px;color:#acb5c9;margin-top:12px">{{ $a->created_at?->format('Y-m-d H:i') }}</div>
    </div>
@empty
    <div class="panel"><p style="color:#6c757d">暂无公告</p></div>
@endforelse
@endsection
