@extends('layouts.admin')
@section('title', '帮助中心')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-book text-primary"></i> 帮助中心 <span class="text-muted" style="font-size:13px;font-weight:400">共 {{ $items->count() }} 篇</span></h4>
    <a href="/admin/help/create" class="btn adm-btn"><i class="fas fa-plus"></i> 新增文档</a>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>ID</th><th>分类</th><th>标题</th><th>状态</th><th>排序</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($items as $a)
            <tr>
                <td class="text-muted">#{{ $a->id }}</td>
                <td><span class="adm-pill muted">{{ $a->category }}</span></td>
                <td style="color:#34395e;font-weight:600">{{ $a->title }}</td>
                <td>@if($a->published)<span class="adm-pill ok">已发布</span>@else<span class="adm-pill muted">草稿</span>@endif</td>
                <td class="text-muted">{{ $a->sort }}</td>
                <td>
                    <a href="/admin/help/{{ $a->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                    <form method="POST" action="/admin/help/{{ $a->id }}" class="d-inline" data-dgr="确认删除该文档？">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="6"><div class="adm-empty"><i class="fas fa-book fa-2x mb-2 d-block"></i>暂无文档，点右上角「新增文档」</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted" style="font-size:12px;margin-top:8px">提示：同一「分类」下的文档会在 App 里归到一组;「排序」越大越靠前。留空/停用的不在 App 显示。</p>
@endsection
