@extends('layouts.admin')
@section('title', '崩溃详情')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-bug text-primary"></i> 崩溃详情 <span class="text-muted" style="font-size:13px;font-weight:400">共 {{ $total }} 次(显示最近 {{ $items->count() }} 条)</span></h4>
    <a href="/admin/system/crashes" class="btn btn-light" style="border-radius:9px"><i class="fas fa-arrow-left"></i> 返回列表</a>
</div>

@foreach($items as $it)
<div class="card adm-panel" style="margin-bottom:14px">
    <div style="padding:14px 18px">
        <div style="font-weight:700;color:#d04d4d;margin-bottom:6px">{{ $it->message ?: '(空)' }}</div>
        <div class="text-muted" style="font-size:12.5px;margin-bottom:10px">
            {{ $it->created_at->format('Y-m-d H:i:s') }}
            · {{ $it->platform ?: '—' }} {{ $it->os_version }}
            · {{ trim($it->brand.' '.$it->model) ?: '未知机型' }}
            · App v{{ $it->app_version ?: '—' }}
            · {{ $it->user ? '用户 #'.$it->user->id.' '.$it->user->username : '游客' }}
            · {{ $it->ip }}
        </div>
        @if($it->stack)
        <pre style="background:#1e1e2d;color:#d6d6e6;border-radius:8px;padding:12px 14px;font-size:12px;line-height:1.5;overflow:auto;max-height:320px;margin:0">{{ $it->stack }}</pre>
        @endif
    </div>
</div>
@endforeach
@endsection
