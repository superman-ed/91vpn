@extends('layouts.admin')
@section('title', '优惠券管理')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-ticket-alt text-primary"></i> 优惠券管理 <span class="text-muted" style="font-size:13px;font-weight:400">共 {{ $totalCount }} 张 · 有效 {{ $activeCount }} · 已核销 {{ $totalUsed }}</span></h4>
    <div class="adm-tools">
        <button type="button" class="btn btn-light" style="border-radius:9px" onclick="document.getElementById('batchBox').style.display='block';this.style.display='none'"><i class="fas fa-layer-group"></i> 批量生成</button>
        <a href="/admin/coupons/create" class="btn adm-btn"><i class="fas fa-plus"></i> 生成优惠券</a>
    </div>
</div>

<form method="GET" class="adm-search adm-tools" style="margin-bottom:16px">
    <input name="q" value="{{ $q }}" class="form-control" placeholder="搜索券码 / 备注" style="min-width:180px">
    <select name="type" class="form-control" style="width:auto">
        <option value="">全部类型</option>
        <option value="percent" @selected($type === 'percent')>百分比折扣</option>
        <option value="amount" @selected($type === 'amount')>固定减</option>
    </select>
    <select name="status" class="form-control" style="width:auto">
        <option value="">全部状态</option>
        <option value="active" @selected($status === 'active')>有效</option>
        <option value="disabled" @selected($status === 'disabled')>已停用</option>
        <option value="expired" @selected($status === 'expired')>已过期</option>
    </select>
    <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
    @if($q || $type || $status)<a href="/admin/coupons" class="btn btn-light" style="border-radius:9px">清除</a>@endif
</form>

@if(session('status'))<div class="alert alert-success" style="border-radius:10px">{{ session('status') }}</div>@endif

{{-- 批量生成 --}}
<div id="batchBox" class="card adm-panel" style="margin-bottom:18px;{{ $errors->has('count') || old('count') ? '' : 'display:none' }}">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-layer-group text-primary"></i> 批量生成（活动发券）</h4></div>
    <form method="POST" action="/admin/coupons/batch" style="padding:14px 20px 18px">@csrf
        <div class="row">
            <div class="form-group col-md-2"><label style="font-size:13px;color:#7a869a;font-weight:600">数量</label><input name="count" type="number" min="1" max="500" value="{{ old('count', 20) }}" class="form-control" style="border-radius:9px" required></div>
            <div class="form-group col-md-2"><label style="font-size:13px;color:#7a869a;font-weight:600">码前缀（选填）</label><input name="prefix" value="{{ old('prefix') }}" class="form-control" placeholder="如 SPRING" style="border-radius:9px;font-family:monospace"></div>
            <div class="form-group col-md-2"><label style="font-size:13px;color:#7a869a;font-weight:600">类型</label><select name="type" class="form-control" style="border-radius:9px"><option value="percent">百分比折扣</option><option value="amount">固定减(元)</option></select></div>
            <div class="form-group col-md-2"><label style="font-size:13px;color:#7a869a;font-weight:600">额度</label><input name="value" type="number" step="0.01" min="0" value="{{ old('value', 10) }}" class="form-control" style="border-radius:9px" required></div>
            <div class="form-group col-md-2"><label style="font-size:13px;color:#7a869a;font-weight:600">每张可用次数</label><input name="max_use" type="number" value="{{ old('max_use', 1) }}" class="form-control" style="border-radius:9px"><small class="text-muted" style="font-size:11px">-1=无限</small></div>
            <div class="form-group col-md-2"><label style="font-size:13px;color:#7a869a;font-weight:600">到期日（选填）</label><input name="expires_at" type="date" value="{{ old('expires_at') }}" class="form-control" style="border-radius:9px"></div>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <span style="font-size:13px;color:#7a869a;font-weight:600">适用时长(不勾=全部)：</span>
            @foreach(['month' => '月付', 'quarter' => '季付', 'half_year' => '半年', 'year' => '年付'] as $k => $label)
            <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="periods[]" value="{{ $k }}"> {{ $label }}</label>
            @endforeach
            <button class="btn adm-btn" style="border-radius:9px;margin-left:auto"><i class="fas fa-bolt"></i> 生成</button>
        </div>
        @error('count')<div class="text-danger" style="font-size:12.5px;margin-top:6px">{{ $message }}</div>@enderror
        @error('prefix')<div class="text-danger" style="font-size:12.5px;margin-top:6px">{{ $message }}</div>@enderror
    </form>
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
                <td>
                    <div style="font-size:13px;color:#34395e">{{ $c->used }} / {{ $c->max_use < 0 ? '∞' : $c->max_use }}</div>
                    @if($c->max_use > 0)
                    @php $pct = min(100, round($c->used / $c->max_use * 100)); @endphp
                    <div style="height:4px;background:#eef1f8;border-radius:3px;margin-top:3px;max-width:90px;overflow:hidden"><div style="height:100%;width:{{ $pct }}%;background:{{ $pct >= 100 ? '#fc544b' : '#6777ef' }}"></div></div>
                    @endif
                </td>
                <td class="text-muted">{{ $c->expires_at?->format('Y-m-d') ?? '永久' }}</td>
                <td>
                    @if($c->enabled)<span class="adm-pill ok">启用</span>@else<span class="adm-pill muted">停用</span>@endif
                    @if($c->show_on_checkout)<span class="adm-pill info">收银台展示</span>@endif
                </td>
                <td>
                    <a href="/admin/coupons/{{ $c->id }}/edit" class="btn btn-outline-primary btn-sm">编辑</a>
                    <form method="POST" action="/admin/coupons/{{ $c->id }}" class="d-inline" data-dgr="确认删除该优惠券？">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="9"><div class="adm-empty"><i class="fas fa-ticket-alt fa-2x mb-2 d-block"></i>暂无优惠券，点右上角「生成优惠券」</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pager', ['p' => $coupons])
</div>
@endsection
