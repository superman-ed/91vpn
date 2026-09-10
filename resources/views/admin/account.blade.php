@extends('layouts.admin')
@section('title', '修改密码')
@section('content')
<div class="row">
  <div class="col-12 col-md-6 col-lg-5">
    <div class="card">
      <div class="card-header"><h4>修改登录密码</h4></div>
      <div class="card-body">

        @if (session('status'))
          <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.account.password') }}" autocomplete="off">
          @csrf
          <div class="form-group">
            <label>当前密码</label>
            <input type="password" name="current_password"
                   class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="form-group">
            <label>新密码 <span class="text-muted">(至少 8 位)</span></label>
            <input type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="form-group">
            <label>确认新密码</label>
            <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn-primary">保存</button>
        </form>

      </div>
    </div>
  </div>
</div>
@endsection
