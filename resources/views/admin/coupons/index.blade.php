@extends('layouts.admin')
@section('title', '优惠券管理')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-ticket-alt text-primary"></i> 优惠券管理 <span class="text-muted" style="font-size:13px;font-weight:400">共 {{ $coupons->count() }} 张</span></h4>
    <a href="/admin/coupons/create" class="btn adm-btn"><i class="fas fa-plus"></i> 生成优惠券</a>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>码</th><th>备注</th><th>类型</th><th>额度</th><th>适用时长</th><th>已用/上限</th><th>到期</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($coupons as $c)
            <tr>
                <td><span class="adm-pill primary" style="font-family:SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.5px">{{ $c->code }}</span></td>
                <td class="text-muted">{{ $c->note ?: '—' }}</td>
                <td>{{ $c->type === 'percent' ? '百分比' : '固定减' }}</td>
                <td style="font-weight:700;color:#34395e">{{ $c->type === 'percent' ? $c->value.'%' : '¥'.$c->value }}</td>
                <td>{{ empty($c->periods) ? '全部' : collect($c->periods)->map(fn ($p) => period_name($p))->implode('、') }}</td>
                <td>{{ $c->used }} / {{ $c->max_use < 0 ? '∞' : $c->max_use }}</td>
                <td class="text-muted">{{ $c->expires_at?->format('Y-m-d') ?? '永久' }}</td>
                <td>
                    @if($c->enabled)<span class="adm-pill ok">启用</span>@else<span class="adm-pill muted">停用</span>@endif
                    @if($c->show_on_checkout)<span class="adm-pill info">收银台展示</span>@endif
                </td>
                <td>
                    <a href="/admin/coupons/{{ $c->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                    <form method="POST" action="/admin/coupons/{{ $c->id }}" class="d-inline" onsubmit="return confirm('确认删除该优惠券？')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="9"><div class="adm-empty"><i class="fas fa-ticket-alt fa-2x mb-2 d-block"></i>暂无优惠券，点右上角「生成优惠券」</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
