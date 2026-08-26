@extends('layouts.admin')
@section('title', '推广代理')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-bullhorn text-primary"></i> 推广代理 <span class="text-muted" style="font-size:13px;font-weight:400">独立推广码 · 归因与业绩（佣金线下结算）</span></h4>
</div>

@if(session('status'))<div class="alert alert-success" style="border-radius:10px">{{ session('status') }}</div>@endif

{{-- 新建推广码 --}}
<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-plus text-primary"></i> 新建推广码</h4></div>
    <form method="POST" action="/admin/promo" style="padding:14px 20px 18px">@csrf
        <div class="row">
            <div class="form-group col-md-4"><label style="font-size:13px;color:#7a869a;font-weight:600">代理 / 渠道名称</label><input name="name" class="form-control" placeholder="如：张三 / TG频道A" style="border-radius:9px" required></div>
            <div class="form-group col-md-3"><label style="font-size:13px;color:#7a869a;font-weight:600">推广码（选填）</label><input name="code" class="form-control" placeholder="留空自动生成" style="border-radius:9px;font-family:monospace"></div>
            <div class="form-group col-md-3"><label style="font-size:13px;color:#7a869a;font-weight:600">内部备注</label><input name="note" class="form-control" placeholder="联系方式/结算约定" style="border-radius:9px"></div>
            <div class="form-group col-md-2 d-flex align-items-end"><button class="btn adm-btn btn-block" style="border-radius:9px">创建</button></div>
        </div>
        @error('code')<div class="text-danger" style="font-size:12.5px;margin-top:-6px">{{ $message }}</div>@enderror
    </form>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>代理 / 渠道</th><th>推广码</th><th>推广链接</th><th>访问(PV/UV)</th><th>注册</th><th>注册率</th><th>付费</th><th>付费率</th><th>带来营收</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($channels as $c)
            @php $s = $stats[$c->code] ?? ['pv' => 0, 'uv' => 0, 'reg' => 0, 'regRate' => 0, 'paid' => 0, 'rate' => 0, 'revenue' => 0]; $link = url('/register?ch='.$c->code); @endphp
            <tr>
                <td><a href="/admin/promo/{{ $c->id }}" style="color:#34395e;font-weight:600">{{ $c->name }}</a>@if($c->note)<div class="text-muted" style="font-size:12px">{{ $c->note }}</div>@endif</td>
                <td><span style="font-family:SFMono-Regular,Menlo,Consolas,monospace;color:#6777ef;font-weight:600">{{ $c->code }}</span></td>
                <td style="max-width:240px"><div style="display:flex;align-items:center;gap:6px"><input value="{{ $link }}" readonly onclick="this.select()" style="flex:1;min-width:0;font-size:12px;border:1px solid #eef0f5;border-radius:7px;padding:4px 8px;color:#54667a"><button type="button" class="btn btn-light btn-sm" style="border-radius:7px" onclick="navigator.clipboard.writeText('{{ $link }}');this.innerText='已复制'">复制</button></div></td>
                <td class="text-muted" style="font-size:12.5px">{{ $s['pv'] }} / {{ $s['uv'] }}</td>
                <td style="font-weight:600">{{ $s['reg'] }}</td>
                <td>@php $rc = $s['regRate'] >= 15 ? '#2fa84f' : ($s['regRate'] >= 5 ? '#e6912a' : '#98a6ad'); @endphp<span style="color:{{ $rc }};font-weight:600">{{ $s['regRate'] }}%</span></td>
                <td>{{ $s['paid'] }}</td>
                <td>@php $col = $s['rate'] >= 20 ? '#2fa84f' : ($s['rate'] >= 8 ? '#e6912a' : '#98a6ad'); @endphp<span style="color:{{ $col }};font-weight:700">{{ $s['rate'] }}%</span></td>
                <td style="font-weight:700;color:#e6960f">¥{{ number_format($s['revenue'], 2) }}</td>
                <td>@if($c->enabled)<span class="adm-pill ok">启用</span>@else<span class="adm-pill muted">停用</span>@endif</td>
                <td style="white-space:nowrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="editPromo({{ $c->id }}, @js($c->name), @js($c->note), {{ $c->enabled ? 'true' : 'false' }})">编辑</button>
                    <form method="POST" action="/admin/promo/{{ $c->id }}" class="d-inline" data-dgr="删除该推广码？历史归因保留">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">删除</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="11"><div class="adm-empty"><i class="fas fa-bullhorn fa-2x mb-2 d-block"></i>还没有推广码，上方新建一个分给你的代理</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- 编辑弹层 --}}
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1090;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:14px;width:420px;max-width:92vw;padding:22px">
        <h5 style="font-weight:700;color:#34395e;margin-bottom:16px">编辑推广码</h5>
        <form method="POST" id="editForm">@csrf @method('PUT')
            <div class="form-group"><label style="font-size:13px;color:#7a869a;font-weight:600">名称</label><input name="name" id="ed-name" class="form-control" style="border-radius:9px" required></div>
            <div class="form-group"><label style="font-size:13px;color:#7a869a;font-weight:600">备注</label><input name="note" id="ed-note" class="form-control" style="border-radius:9px"></div>
            <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0"><input type="checkbox" name="enabled" id="ed-enabled" value="1" style="width:16px;height:16px"> 启用</label></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px"><button type="button" class="btn btn-light" style="border-radius:9px" onclick="document.getElementById('editModal').style.display='none'">取消</button><button class="btn adm-btn" style="border-radius:9px">保存</button></div>
        </form>
    </div>
</div>
<script>
function editPromo(id, name, note, enabled) {
    var f = document.getElementById('editForm');
    f.action = '/admin/promo/' + id;
    document.getElementById('ed-name').value = name;
    document.getElementById('ed-note').value = note || '';
    document.getElementById('ed-enabled').checked = enabled;
    document.getElementById('editModal').style.display = 'flex';
}
</script>
@endsection
