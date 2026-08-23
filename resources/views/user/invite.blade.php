@extends('layouts.user')
@section('title', '邀请返利')
@section('content')
<div class="row">
    <div class="col-12 col-md-4"><div class="card card-statistic-1"><div class="card-icon bg-primary"><i class="fas fa-ticket-alt"></i></div><div class="card-wrap"><div class="card-header"><h4>我的邀请码</h4></div><div class="card-body">{{ $user->ref_code }}</div></div></div></div>
    <div class="col-12 col-md-4"><div class="card card-statistic-1"><div class="card-icon bg-success"><i class="fas fa-users"></i></div><div class="card-wrap"><div class="card-header"><h4>已邀请</h4></div><div class="card-body">{{ $downlines->count() }} 人</div></div></div></div>
    <div class="col-12 col-md-4"><div class="card card-statistic-1"><div class="card-icon bg-warning"><i class="fas fa-coins"></i></div><div class="card-wrap"><div class="card-header"><h4>累计返利</h4></div><div class="card-body">¥{{ number_format($totalPayback,2) }}</div></div></div></div>
</div>
<div class="card">
    <div class="card-header"><h4>邀请链接</h4></div>
    <div class="card-body">
        <p class="text-muted">好友通过此链接注册即成为你的下线，其消费你可获得 20% 返利。</p>
        <div class="alert alert-light" style="word-break:break-all"><code>{{ $inviteUrl }}</code></div>
        <button class="btn btn-outline-primary" onclick="navigator.clipboard.writeText('{{ $inviteUrl }}');this.innerHTML='<i class=\'fas fa-check\'></i> 已复制'"><i class="fas fa-copy"></i> 复制邀请链接</button>
    </div>
</div>
<div class="row">
    <div class="col-12 col-lg-6"><div class="card"><div class="card-header"><h4>我的下线</h4></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>邮箱</th><th>注册时间</th></tr></thead><tbody>
        @forelse($downlines as $d)<tr><td>{{ $d->email }}</td><td>{{ $d->created_at?->format('Y-m-d') }}</td></tr>@empty<tr><td colspan="2" class="text-muted">还没有邀请任何人</td></tr>@endforelse
    </tbody></table></div></div></div></div>
    <div class="col-12 col-lg-6"><div class="card"><div class="card-header"><h4>返利记录</h4></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>来自</th><th>金额</th><th>时间</th></tr></thead><tbody>
        @forelse($paybacks as $p)<tr><td>{{ $p->fromUser?->email ?? '—' }}</td><td class="text-success">+¥{{ number_format($p->amount,2) }}</td><td>{{ $p->created_at?->format('Y-m-d H:i') }}</td></tr>@empty<tr><td colspan="3" class="text-muted">暂无返利</td></tr>@endforelse
    </tbody></table></div></div></div></div>
</div>
@endsection
