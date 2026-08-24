@extends('layouts.user')
@section('title', '账号设置')
@section('head')
<meta name="turbo-cache-control" content="no-cache">
<style>
.acc-hero {
    border: none; border-radius: 14px; overflow: hidden; color: #fff;
    background: linear-gradient(135deg, #6777ef 0%, #5a67e8 60%, #4b56d6 100%);
    box-shadow: 0 10px 30px rgba(103,119,239,.28);
}
.acc-hero .card-body { padding: 24px 26px; display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
.acc-avatar { width: 64px; height: 64px; border-radius: 50%; background: rgba(255,255,255,.2); display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; flex-shrink: 0; }
.acc-id .name { font-size: 20px; font-weight: 800; }
.acc-id .email { font-size: 13px; opacity: .88; }
.acc-badges { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
.acc-badge { background: rgba(255,255,255,.16); border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600; }

.acc-card { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); height: 100%; }
.acc-card .card-header { border-bottom: 1px solid #f1f3fb; padding: 16px 22px; }
.acc-card .card-header h4 { font-size: 15px; font-weight: 700; color: #34395e; margin: 0; }
.acc-card .card-body { padding: 22px; }
.acc-card label { font-size: 13px; color: #7a869a; font-weight: 600; }
.acc-card .form-control { border-radius: 9px; border-color: #eef0f5; }
.acc-card .form-control:disabled { background: #f6f7fb; }
.acc-btn { border-radius: 9px; font-weight: 700; background: linear-gradient(135deg,#6777ef,#5a67e8); border: none; }
.acc-btn:hover { filter: brightness(1.05); }

.acc-panel { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; }
.acc-panel .card-header { border-bottom: 1px solid #f1f3fb; padding: 16px 22px; }
.acc-panel .card-header h4 { font-size: 15px; font-weight: 700; color: #34395e; margin: 0; }
.acc-table { margin: 0; }
.acc-table thead th { border: none; background: #fafbff; color: #98a6ad; font-size: 12px; font-weight: 600; padding: 12px 22px; }
.acc-table tbody td { border-top: 1px solid #f4f6fb; padding: 13px 22px; font-size: 13.5px; color: #54667a; vertical-align: middle; }
.acc-table tbody tr:hover { background: #fafbff; }
.acc-empty { text-align: center; color: #98a6ad; padding: 34px 0; }
.acc-ip { font-family: SFMono-Regular, Menlo, Consolas, monospace; color: #34395e; }
</style>
@endsection
@section('content')
<div class="card acc-hero mb-4">
    <div class="card-body">
        <div class="acc-avatar">{{ mb_strtoupper(mb_substr($user->name ?: $user->email, 0, 1)) }}</div>
        <div class="acc-id">
            <div class="name">{{ $user->name }}</div>
            <div class="email"><i class="fas fa-envelope"></i> {{ $user->email }}</div>
            <div class="acc-badges">
                <span class="acc-badge"><i class="fas fa-crown"></i> {{ class_name($user->class) }}</span>
                <span class="acc-badge"><i class="fas fa-clock"></i> {{ $user->membershipText() }}</span>
                <span class="acc-badge"><i class="fas fa-calendar-alt"></i> 注册于 {{ $user->created_at?->format('Y-m-d') }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-6 mb-4">
        <div class="card acc-card">
            <div class="card-header"><h4><i class="fas fa-user-edit text-primary"></i> 基本信息</h4></div>
            <div class="card-body">
                <form method="POST" action="/user/account/profile">@csrf
                    <div class="form-group">
                        <label>邮箱</label>
                        <input class="form-control" value="{{ $user->email }}" disabled>
                        <small class="text-muted">邮箱为登录账号，暂不支持修改。</small>
                    </div>
                    <div class="form-group">
                        <label>昵称</label>
                        <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn acc-btn text-white"><i class="fas fa-save"></i> 保存昵称</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6 mb-4">
        <div class="card acc-card">
            <div class="card-header"><h4><i class="fas fa-shield-alt text-primary"></i> 修改登录密码</h4></div>
            <div class="card-body">
                <form method="POST" action="/user/account/password">@csrf
                    <div class="form-group">
                        <label>当前密码</label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>新密码</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">至少 8 位。</small>
                    </div>
                    <div class="form-group">
                        <label>确认新密码</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button class="btn acc-btn text-white"><i class="fas fa-key"></i> 修改密码</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card acc-panel">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h4><i class="fas fa-history text-primary"></i> 最近登录记录</h4>
        <small class="text-muted">发现陌生 IP，请立即修改密码</small>
    </div>
    <div class="table-responsive">
        <table class="table acc-table">
            <thead><tr><th>IP 地址</th><th>登录地点</th><th>时间</th></tr></thead>
            <tbody>
            @forelse($loginLogs as $log)
            <tr>
                <td><span class="acc-ip">{{ $log->ip }}</span></td>
                <td>{{ $log->location ?: '—' }}</td>
                <td class="text-muted">{{ $log->logged_at?->format('Y-m-d H:i:s') }}</td>
            </tr>
            @empty<tr><td colspan="3"><div class="acc-empty"><i class="fas fa-clock fa-2x mb-2 d-block"></i>暂无登录记录</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
