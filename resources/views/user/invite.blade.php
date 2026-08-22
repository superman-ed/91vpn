@extends('layouts.user')
@section('title', '邀请注册')
@section('content')
<div class="cards">
    <div class="card"><div class="k">我的邀请码</div><div class="v" style="font-size:22px">{{ $user->ref_code }}</div></div>
    <div class="card"><div class="k">已邀请人数</div><div class="v">{{ $downlines->count() }}</div></div>
    <div class="card"><div class="k">累计返利</div><div class="v"><small>¥</small>{{ number_format($totalPayback, 2) }}</div></div>
</div>

<div class="panel">
    <h3>邀请链接</h3>
    <p style="font-size:13px;color:#6c757d">好友通过此链接注册即成为你的下线，其消费你可获得 20% 返利（进余额）。</p>
    <p><code>{{ $inviteUrl }}</code></p>
    <button class="btn ghost" onclick="navigator.clipboard.writeText('{{ $inviteUrl }}');this.textContent='已复制'">复制邀请链接</button>
</div>

<div class="panel">
    <h3>我的下线</h3>
    <table>
        <tr><th>邮箱</th><th>注册时间</th></tr>
        @forelse($downlines as $d)
        <tr><td>{{ $d->email }}</td><td>{{ $d->created_at?->format('Y-m-d') }}</td></tr>
        @empty<tr><td colspan="2" style="color:#acb5c9">还没有邀请任何人</td></tr>@endforelse
    </table>
</div>

<div class="panel">
    <h3>返利记录</h3>
    <table>
        <tr><th>来自</th><th>金额</th><th>时间</th></tr>
        @forelse($paybacks as $p)
        <tr><td>{{ $p->fromUser?->email ?? '—' }}</td><td>+¥{{ number_format($p->amount, 2) }}</td><td>{{ $p->created_at?->format('Y-m-d H:i') }}</td></tr>
        @empty<tr><td colspan="3" style="color:#acb5c9">暂无返利</td></tr>@endforelse
    </table>
</div>
@endsection
