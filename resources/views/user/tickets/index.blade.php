@extends('layouts.user')
@section('title', '工单支持')
@section('content')
<div class="card">
    <div class="card-header"><h4>我的工单</h4><div class="card-header-action"><a href="/user/ticket/create" class="btn btn-primary btn-sm">新建工单</a></div></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>标题</th><th>状态</th><th>最后更新</th><th></th></tr></thead>
            <tbody>
            @forelse($tickets as $t)
            <tr><td>{{ $t->subject }}</td>
                <td>@if($t->status==='open')<span class="badge badge-primary">进行中</span>@else<span class="badge badge-secondary">已关闭</span>@endif</td>
                <td>{{ $t->updated_at?->format('Y-m-d H:i') }}</td>
                <td><a href="/user/ticket/{{ $t->id }}" class="btn btn-outline-primary btn-sm">查看</a></td></tr>
            @empty<tr><td colspan="4" class="text-muted">暂无工单</td></tr>@endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
