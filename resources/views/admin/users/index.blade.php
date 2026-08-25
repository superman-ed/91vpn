@extends('layouts.admin')
@section('title', '用户管理')
@section('content')
@php $tabs = ['' => '全部', 'member' => '会员', 'free' => '免费', 'expired' => '已过期', 'banned' => '封禁']; @endphp
<div class="adm-head">
    <h4><i class="fas fa-users text-primary"></i> 用户管理</h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="hidden" name="status" value="{{ $status }}">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索邮箱 / 昵称" style="min-width:200px">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 搜索</button>
        @if($q)<a href="/admin/users{{ $status ? '?status='.$status : '' }}" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>
<div class="adm-tools" style="margin-bottom:18px">
    @foreach($tabs as $k => $label)
    <a href="/admin/users{{ $k ? '?status='.$k : '' }}" class="btn btn-sm {{ (string) $status === $k ? 'adm-btn' : 'btn-light' }}" style="border-radius:9px">{{ $label }} <span style="opacity:.7">{{ $k ? ($counts[$k] ?? 0) : $counts['all'] }}</span></a>
    @endforeach
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>ID</th><th>用户</th><th>等级</th><th>剩余流量</th><th>到期</th><th>余额</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($users as $u)
            <tr>
                <td class="text-muted">#{{ $u->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $u->email }}@if($u->name)<br><span class="text-muted" style="font-weight:400;font-size:12px">{{ $u->name }}</span>@endif</td>
                <td><span class="adm-pill primary">{{ class_name($u->class) }}</span></td>
                <td>{{ number_format(bytes_to_gb(max(0, $u->transfer_enable - $u->u - $u->d)), 1) }} GB</td>
                <td class="text-muted">{{ $u->class_expire?->format('Y-m-d') ?? '—' }}</td>
                <td>¥{{ number_format($u->money, 2) }}</td>
                <td>
                    @if($u->banned)<span class="adm-pill danger">封禁</span>
                    @elseif($u->is_admin)<span class="adm-pill primary">管理员</span>
                    @else<span class="adm-pill ok">正常</span>@endif
                </td>
                <td>
                    <a href="/admin/users/{{ $u->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                    <form method="POST" action="/admin/users/{{ $u->id }}/toggle-ban" class="d-inline" onsubmit="return confirm('{{ $u->banned ? '确认解封？' : '确认封禁该用户？' }}')">@csrf<button class="btn btn-{{ $u->banned ? 'success' : 'outline-danger' }} btn-sm">{{ $u->banned ? '解封' : '封禁' }}</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="8"><div class="adm-empty"><i class="fas fa-user-slash fa-2x mb-2 d-block"></i>没有找到用户</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())<div class="adm-foot">{{ $users->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
