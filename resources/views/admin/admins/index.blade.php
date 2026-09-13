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
            <thead><tr><th>ID</th><th>账户名</th><th>昵称</th><th>角色</th><th>注册时间</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($admins as $u)
            <tr>
                <td class="text-muted">#{{ $u->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $u->username ?: $u->email }} @if($u->id === auth()->id())<span class="adm-pill primary">我</span>@endif</td>
                <td>{{ $u->name ?: '—' }}</td>
                {{-- `[!!]` 改角色是"改别人能做什么"的动作，所以就地可改但必须留审计。
                     两条保护在控制器里：不能改自己的、不能降掉最后一个超管 ——
                     这类错误发生之后没有人能修，只能在发生之前拦。 --}}
                <td>
                  @if($u->id === auth()->id())
                    <span class="adm-pill primary">{{ $roles[$u->admin_role] ?? '未设置' }}</span>
                    <div class="hint">不能改自己的角色</div>
                  @else
                    <form method="POST" action="/admin/admins/{{ $u->id }}/role" class="d-flex" style="gap:6px">@csrf
                      <select name="admin_role" class="form-control form-control-sm" style="width:auto">
                        @foreach($roles as $k => $label)
                          <option value="{{ $k }}" @selected($u->admin_role === $k)>{{ $label }}</option>
                        @endforeach
                      </select>
                      <button class="btn btn-outline-primary btn-sm">保存</button>
                    </form>
                  @endif
                </td>
                <td class="text-muted">{{ $u->created_at?->format('Y-m-d') }}</td>
                <td>
                    @if($u->id === auth()->id())
                        <span class="text-muted">—</span>
                    @else
                        <form method="POST" action="/admin/admins/{{ $u->id }}" class="d-inline" data-dgr="撤销后 {{ $u->username ?: $u->email }} 将失去所有后台权限，变回普通用户。" data-dgr-word="REVOKE">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">撤销管理员</button></form>
                    @endif
                </td>
            </tr>
            @empty<tr><td colspan="6"><div class="adm-empty"><i class="fas fa-user-shield fa-2x mb-2 d-block"></i>暂无管理员</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
