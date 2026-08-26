@extends('layouts.admin')
@section('title', '管理员')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-user-shield text-primary"></i> 管理员 <span class="text-muted" style="font-size:13px;font-weight:400">共 {{ $admins->count() }} 人</span></h4>
    <a href="/admin/admins/create" class="btn adm-btn"><i class="fas fa-plus"></i> 添加管理员</a>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>ID</th><th>邮箱</th><th>昵称</th><th>注册时间</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($admins as $u)
            <tr>
                <td class="text-muted">#{{ $u->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $u->email }} @if($u->id === auth()->id())<span class="adm-pill primary">我</span>@endif</td>
                <td>{{ $u->name ?: '—' }}</td>
                <td class="text-muted">{{ $u->created_at?->format('Y-m-d') }}</td>
                <td>
                    @if($u->id === auth()->id())
                        <span class="text-muted">—</span>
                    @else
                        <form method="POST" action="/admin/admins/{{ $u->id }}" class="d-inline" data-dgr="撤销后 {{ $u->email }} 将失去所有后台权限，变回普通用户。" data-dgr-word="REVOKE">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">撤销管理员</button></form>
                    @endif
                </td>
            </tr>
            @empty<tr><td colspan="5"><div class="adm-empty"><i class="fas fa-user-shield fa-2x mb-2 d-block"></i>暂无管理员</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
