@extends('layouts.admin')
@section('title', '公告管理')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-bullhorn text-primary"></i> 公告管理 <span class="text-muted" style="font-size:13px;font-weight:400">共 {{ $items->count() }} 条</span></h4>
    <a href="/admin/announcements/create" class="btn adm-btn"><i class="fas fa-plus"></i> 发布公告</a>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>ID</th><th>标题</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($items as $a)
            <tr>
                <td class="text-muted">#{{ $a->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $a->title }}</td>
                <td>@if($a->published)<span class="adm-pill ok">已发布</span>@else<span class="adm-pill muted">草稿</span>@endif</td>
                <td class="text-muted">{{ $a->created_at?->format('Y-m-d H:i') }}</td>
                <td>
                    <a href="/admin/announcements/{{ $a->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                    <form method="POST" action="/admin/announcements/{{ $a->id }}" class="d-inline" data-dgr="确认删除该公告？">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="5"><div class="adm-empty"><i class="fas fa-bullhorn fa-2x mb-2 d-block"></i>暂无公告，点右上角「发布公告」</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
