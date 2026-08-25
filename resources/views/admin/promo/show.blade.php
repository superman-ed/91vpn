@extends('layouts.admin')
@section('title', '推广代理 · '.$channel->name)
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-bullhorn text-primary"></i> {{ $channel->name }} <span style="font-family:SFMono-Regular,Menlo,Consolas,monospace;color:#6777ef;font-size:14px">{{ $channel->code }}</span></h4>
    <a href="/admin/promo" class="btn btn-light" style="border-radius:9px"><i class="fas fa-arrow-left"></i> 返回列表</a>
</div>

<div class="card adm-panel">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0">该代理带来的用户 <span class="text-muted" style="font-weight:400;font-size:12px">（共 {{ $users->total() }} 人）</span></h4></div>
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>用户</th><th>注册时间</th><th>会员</th><th>累计付费</th></tr></thead>
            <tbody>
            @forelse($users as $u)
            <tr>
                <td style="color:#34395e;font-weight:600">{{ $u->email }}</td>
                <td class="text-muted">{{ $u->created_at?->format('Y-m-d H:i') }}</td>
                <td>@if($u->class > 0 && $u->class_expire > now())<span class="adm-pill ok">会员</span>@elseif($u->class > 0)<span class="adm-pill warn">已过期</span>@else<span class="adm-pill muted">免费</span>@endif</td>
                <td style="font-weight:700;color:{{ $u->paid_amount > 0 ? '#e6960f' : '#98a6ad' }}">¥{{ number_format((float) $u->paid_amount, 2) }}</td>
            </tr>
            @empty<tr><td colspan="4"><div class="adm-empty">该推广码还没带来注册用户</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())<div class="adm-foot">{{ $users->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
