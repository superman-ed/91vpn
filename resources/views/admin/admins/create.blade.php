@extends('layouts.admin')
@section('title', '添加管理员')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-user-shield text-primary"></i> 添加管理员</h4>
    <a href="/admin/admins" class="btn btn-light" style="border-radius:9px">返回</a>
</div>

<form method="POST" action="/admin/admins" class="adm-form">@csrf
    <div class="card adm-form-card">
        <div class="card-header"><span class="ic"><i class="fas fa-user-plus"></i></span><h4>管理员账号</h4></div>
        <div class="card-body">
            <p class="form-tip">若账户名已是注册用户，将直接把 TA 提升为管理员（无需填密码）；若不存在，则新建一个管理员账号（需填密码）。后台用账户名登录。</p>
            <div class="row">
                <div class="form-group col-md-6">
                    <label>账户名</label>
                    <input name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" placeholder="4–20 位字母/数字/下划线" required>
                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group col-md-6">
                    <label>昵称（选填）</label>
                    <input name="name" value="{{ old('name') }}" class="form-control" placeholder="默认「管理员」">
                </div>
                <div class="form-group col-md-6">
                    <label>密码（新建账号时必填，至少 8 位）</label>
                    <input name="password" type="password" class="form-control @error('password') is-invalid @enderror" placeholder="提升已有用户可留空">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <button class="btn adm-btn"><i class="fas fa-check"></i> 确认添加</button>
        </div>
    </div>
</form>
@endsection
