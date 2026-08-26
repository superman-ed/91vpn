@extends('layouts.admin')
@section('title', '套餐管理')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-box text-primary"></i> 套餐管理 <span class="text-muted" style="font-size:13px;font-weight:400">共 {{ $plans->count() }} 个</span></h4>
    <a href="/admin/plans/create" class="btn adm-btn"><i class="fas fa-plus"></i> 添加套餐</a>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>ID</th><th>名称</th><th>周期</th><th>价格</th><th>流量</th><th>等级</th><th>限速/设备</th><th>时长</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($plans as $p)
            <tr>
                <td class="text-muted">#{{ $p->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $p->name }} @if($p->is_data_pack)<span class="adm-pill info">流量包</span>@endif</td>
                <td><span class="adm-pill muted">{{ period_name($p->period) }}</span></td>
                <td style="font-weight:700;color:#34395e">¥{{ rtrim(rtrim(number_format($p->price, 2), '0'), '.') }}</td>
                <td>{{ $p->transfer_gb }}GB @if($p->reset_type === 'none' && ! $p->is_data_pack)<span class="adm-pill primary">总量</span>@endif</td>
                <td>{{ class_name($p->class) }}</td>
                <td class="text-muted">{{ $p->speed_limit ?: '不限' }}{{ $p->speed_limit ? 'M' : '' }} / {{ $p->ip_limit ?: '不限' }}{{ $p->ip_limit ? '台' : '' }}</td>
                <td class="text-muted">{{ $p->duration_days }}天</td>
                <td>
                    <form method="POST" action="/admin/plans/{{ $p->id }}/toggle-sale" class="d-inline">@csrf
                        @if($p->on_sale)<button class="btn btn-sm" style="border:none;background:#e9f9ed;color:#2fa84f;border-radius:20px;font-weight:600;font-size:12px" title="点击下架">在售</button>
                        @else<button class="btn btn-sm" style="border:none;background:#f2f3f5;color:#98a6ad;border-radius:20px;font-weight:600;font-size:12px" title="点击上架">下架</button>@endif
                    </form>
                </td>
                <td style="white-space:nowrap">
                    <form method="POST" action="/admin/plans/{{ $p->id }}/move" class="d-inline">@csrf<input type="hidden" name="dir" value="up"><button class="btn btn-light btn-sm" title="上移" style="padding:2px 7px"><i class="fas fa-arrow-up"></i></button></form>
                    <form method="POST" action="/admin/plans/{{ $p->id }}/move" class="d-inline">@csrf<input type="hidden" name="dir" value="down"><button class="btn btn-light btn-sm" title="下移" style="padding:2px 7px"><i class="fas fa-arrow-down"></i></button></form>
                    <a href="/admin/plans/{{ $p->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                    <form method="POST" action="/admin/plans/{{ $p->id }}" class="d-inline" data-dgr="确认删除该套餐？">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="10"><div class="adm-empty"><i class="fas fa-box-open fa-2x mb-2 d-block"></i>暂无套餐，点右上角「添加套餐」</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
