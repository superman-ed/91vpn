@extends('layouts.admin')
@section('title', '系统健康')
@section('content')
@php
    $badTasks = collect($tasks)->where('status', 'bad')->count();
    $downSvc = collect($services)->where('ok', false)->count();
@endphp
<div class="adm-head">
    <h4><i class="fas fa-heartbeat text-primary"></i> 系统健康 <span class="text-muted" style="font-size:13px;font-weight:400">运维仪表盘</span></h4>
    <a href="/admin/system/health" class="btn btn-light" style="border-radius:9px"><i class="fas fa-sync-alt"></i> 刷新</a>
</div>

{{-- 总览 --}}
@php $allGood = $badTasks === 0 && $downSvc === 0; @endphp
<div class="card adm-panel" style="margin-bottom:18px;border-left:4px solid {{ $allGood ? '#2fa84f' : '#fc544b' }}">
    <div style="padding:16px 20px;display:flex;align-items:center;gap:12px">
        <span style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;background:{{ $allGood ? '#e9f9ed' : '#fdecea' }};color:{{ $allGood ? '#2fa84f' : '#fc544b' }}"><i class="fas fa-{{ $allGood ? 'check' : 'exclamation-triangle' }}"></i></span>
        <div>
            <div style="font-size:15px;font-weight:700;color:#34395e">{{ $allGood ? '系统运行正常' : '发现异常，请检查' }}</div>
            <div class="text-muted" style="font-size:12.5px">{{ $badTasks }} 个定时任务异常 · {{ $downSvc }} 个服务不可用</div>
        </div>
    </div>
</div>

{{-- 定时任务 --}}
<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-clock text-primary"></i> 定时任务 <span class="text-muted" style="font-weight:400;font-size:12px">（挂了会静默停摆，最该盯）</span></h4></div>
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>任务</th><th>频率</th><th>上次运行</th><th>状态</th></tr></thead>
            <tbody>
            @foreach($tasks as $t)
            <tr>
                <td style="color:#34395e;font-weight:600">{{ $t['name'] }} <span class="text-muted" style="font-size:11px;font-family:monospace">{{ $t['sig'] }}</span></td>
                <td class="text-muted">{{ $t['freq'] }}</td>
                <td class="text-muted">{{ $t['last']?->diffForHumans() ?? '从未运行' }}@if($t['last'])<span style="font-size:11px"> · {{ $t['last']->format('m-d H:i:s') }}</span>@endif</td>
                <td>
                    @if($t['status'] === 'ok')<span class="adm-pill ok">正常</span>
                    @elseif($t['status'] === 'unknown')<span class="adm-pill muted">未运行</span>
                    @else<span class="adm-pill danger">异常</span>@endif
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if(collect($tasks)->every(fn ($t) => $t['status'] === 'unknown'))
    <div style="padding:0 20px 16px"><small class="text-muted">全部"未运行"通常表示 <code>scheduler</code> 容器/cron 还没跑过或未启动——上线后确认 scheduler 在运行。</small></div>
    @endif
</div>

<div class="row">
    <div class="col-lg-6">
        {{-- 基础服务 --}}
        <div class="card adm-panel" style="margin-bottom:18px">
            <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-server text-primary"></i> 基础服务</h4></div>
            <div style="padding:8px 20px 16px">
                @foreach($services as $s)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f4f6fb">
                    <span style="font-size:13.5px;color:#34395e;font-weight:600"><i class="fas fa-circle" style="font-size:8px;color:{{ $s['ok'] ? '#2fa84f' : '#fc544b' }};margin-right:8px"></i>{{ $s['name'] }}</span>
                    <span style="font-size:12.5px;color:{{ $s['ok'] ? '#54667a' : '#fc544b' }};font-family:SFMono-Regular,Menlo,Consolas,monospace">{{ $s['detail'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        {{-- 环境 / 磁盘 --}}
        <div class="card adm-panel" style="margin-bottom:18px">
            <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-microchip text-primary"></i> 环境 / 磁盘</h4></div>
            <div style="padding:10px 20px 16px;font-size:13px;color:#54667a">
                @if($env['disk_used_pct'] !== null)
                <div style="margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;font-size:12.5px"><span>磁盘使用 {{ $env['disk_used_pct'] }}%</span><span>剩余 {{ $env['disk_free'] }}</span></div>
                    <div style="height:7px;background:#eef1f8;border-radius:4px;margin-top:4px;overflow:hidden"><div style="height:100%;width:{{ $env['disk_used_pct'] }}%;background:{{ $env['disk_used_pct'] >= 90 ? '#fc544b' : ($env['disk_used_pct'] >= 75 ? '#e6912a' : '#3fae57') }}"></div></div>
                </div>
                @endif
                <div class="row" style="font-size:12.5px">
                    <div class="col-6 mb-1">PHP：<b style="color:#34395e">{{ $env['php'] }}</b></div>
                    <div class="col-6 mb-1">Laravel：<b style="color:#34395e">{{ $env['laravel'] }}</b></div>
                    <div class="col-6 mb-1">环境：<b style="color:#34395e">{{ $env['env'] }}</b></div>
                    <div class="col-6 mb-1">Debug：<b style="color:{{ str_contains($env['debug'], '开') ? '#fc544b' : '#34395e' }}">{{ $env['debug'] }}</b></div>
                    <div class="col-6 mb-1">缓存：<b style="color:#34395e">{{ $env['cache'] }}</b></div>
                    <div class="col-6 mb-1">队列：<b style="color:#34395e">{{ $env['queue'] }}</b></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 节点心跳 --}}
<div class="card adm-panel">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-network-wired text-primary"></i> 节点心跳 <span class="text-muted" style="font-weight:400;font-size:12px">（3 分钟内有上报算在线）</span></h4></div>
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>节点</th><th>最后心跳</th><th>状态</th></tr></thead>
            <tbody>
            @forelse($nodes as $n)
            <tr>
                <td style="color:#34395e;font-weight:600">{{ $n['name'] }}</td>
                <td class="text-muted">{{ $n['last']?->diffForHumans() ?? '从未上报' }}</td>
                <td>@if($n['online'])<span class="adm-pill ok">在线</span>@else<span class="adm-pill danger">离线/失联</span>@endif</td>
            </tr>
            @empty<tr><td colspan="3"><div class="adm-empty">暂无节点（部署真实节点后自动显示心跳）</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
