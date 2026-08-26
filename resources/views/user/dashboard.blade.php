@extends('layouts.user')
@section('title', '首页')
@section('head')
<style>
.stat-row > [class*="col-"] { display: flex; margin-bottom: 30px; }
.stat-row .stat-card { width: 100%; height: 100%; margin-bottom: 0; }
.stat-card .card-body { padding: 18px 20px; display: flex; flex-direction: column; }
.stat-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.stat-head .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 22px; flex-shrink: 0; }
.stat-head .stat-title { margin: 0; font-size: 15px; font-weight: 600; color: #6c757d; }
.stat-value { font-size: 24px; font-weight: 700; line-height: 1.2; }
.stat-sub-box { background: #f4f6f9; border-radius: 10px; padding: 10px 14px; margin-top: auto; min-height: 60px; display: flex; flex-direction: column; justify-content: center; }
.stat-sub-box .stat-sub { display: block; font-size: 13px; font-weight: 400; color: #6c757d; white-space: nowrap; }
.stat-sub-box .stat-sub + .stat-sub { margin-top: 4px; }
.stat-sub-box a { color: #6777ef; }
</style>
@endsection
@section('header-action')
<form method="POST" action="/user/checkin">@csrf
    @if($checkedIn)
        <button class="btn btn-light" disabled><i class="fas fa-check"></i> 今日已签到</button>
    @else
        <button class="btn btn-primary"><i class="fas fa-calendar-check"></i> 每日签到领流量</button>
    @endif
</form>
@endsection
@section('content')
@php $queued = $user->queuedOrders()->with('plan')->get(); @endphp
@if($queued->isNotEmpty() || $user->canEndCurrentPackage())
<div class="card">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between" style="gap:10px">
        <div class="text-muted">
            @if($queued->isNotEmpty())
                <i class="fas fa-hourglass-half text-info"></i> 有 {{ $queued->count() }} 个套餐排队中，最近一个预计 <strong>{{ $queued->first()->activate_at?->format('Y-m-d H:i') }}</strong> 生效（当前套餐到期后自动切换，详情见<a href="/user/wallet">我的钱包</a>）。
            @else
                <i class="fas fa-check-circle text-success"></i> 当前套餐生效中。
            @endif
        </div>
        @if($user->canEndCurrentPackage())
        <form method="POST" action="/user/subscription/end" onsubmit="return confirm('确定立即结束当前套餐？当前套餐剩余时长将作废，排队套餐立即生效。')">@csrf
            <button class="btn btn-outline-danger btn-sm"><i class="fas fa-power-off"></i> 立即结束当前套餐</button>
        </form>
        @endif
    </div>
</div>
@endif
<div class="row stat-row">
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-head">
                    <span class="stat-icon bg-primary"><i class="fas fa-clock"></i></span>
                    <h4 class="stat-title">会员时长</h4>
                </div>
                <div class="stat-value text-primary">{{ $membership }}</div>
                <div class="stat-sub-box">
                    <span class="stat-sub">{{ $className }}@if($expireDate) · {{ $expireDate }} 到期@endif</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-head">
                    <span class="stat-icon bg-success"><i class="fas fa-signal"></i></span>
                    <h4 class="stat-title">剩余流量</h4>
                </div>
                @if($user->transfer_enable <= 0)
                    <div class="stat-value text-success">未开通</div>
                    <div class="stat-sub-box"><span class="stat-sub">暂无流量套餐</span></div>
                @else
                    <div class="stat-value text-success">{{ number_format($remainGb, 1) }} GB</div>
                    <div class="stat-sub-box">
                        @php $barColor = $usagePercent >= 90 ? 'bg-danger' : ($usagePercent >= 70 ? 'bg-warning' : 'bg-success'); @endphp
                        <div class="progress" style="height:6px;margin-bottom:6px">
                            <div class="progress-bar {{ $barColor }}" role="progressbar" style="width:{{ $usagePercent }}%"></div>
                        </div>
                        <span class="stat-sub">{{ $usagePercent >= 100 ? '已用尽' : '已用 '.$usagePercent.'%' }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-head">
                    <span class="stat-icon bg-warning"><i class="fas fa-wallet"></i></span>
                    <h4 class="stat-title">钱包余额</h4>
                </div>
                <div class="stat-value text-warning">¥{{ number_format($user->money, 2) }}</div>
                <div class="stat-sub-box">
                    <span class="stat-sub">累计获得返利 {{ number_format($rebateTotal, 2) }} 元</span>
                    <span class="stat-sub"><a href="{{ route('user.wallet') }}">我的钱包</a> | <a href="{{ route('user.shop') }}">购买套餐</a></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-head">
                    <span class="stat-icon bg-info"><i class="fas fa-mobile-alt"></i></span>
                    <h4 class="stat-title">设备上限</h4>
                </div>
                <div class="stat-value text-info">{{ $user->onlineDevices() }} / {{ $user->node_ip_limit ?: '∞' }}</div>
                <div class="stat-sub-box">
                    <span class="stat-sub">上次使用时间: {{ $user->lastUsedText() }}</span>
                    <a href="/user/devices" class="stat-sub" style="color:#6777ef;font-weight:600;text-decoration:none">在线设备 <i class="fas fa-angle-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header"><h4 style="color:#6777ef">公告</h4></div>
            <div class="card-body">
                @forelse($announcements as $a)
                    <div class="{{ $loop->last ? '' : 'mb-3' }}" style="background:#f4f6ff;border-left:3px solid #6777ef;border-radius:6px;padding:14px 16px">
                        <div style="font-weight:700;color:#34395e;font-size:15px">{{ $a->title }}</div>
                        <div class="text-muted" style="font-size:12px;margin:2px 0 8px">{{ $a->created_at->format('Y-m-d H:i') }}</div>
                        <div style="font-size:14px;line-height:1.8;color:#34395e">{!! linkify($a->content) !!}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">暂无公告</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><h4 style="color:#6777ef">客户端下载和教程</h4></div>
            <div class="card-body">
                <div class="row text-center">
                    @foreach([['Windows','fab fa-windows'],['macOS','fab fa-apple'],['Android','fab fa-android'],['iOS','fab fa-app-store-ios']] as $p)
                    <div class="col-3 mb-2">
                        <a href="/user/downloads" style="color:#6777ef;text-decoration:none">
                            <i class="{{ $p[1] }}" style="font-size:26px"></i>
                            <div class="text-muted" style="font-size:12px;margin-top:4px">{{ $p[0] }}</div>
                        </a>
                    </div>
                    @endforeach
                </div>
                <a href="/user/downloads" class="btn btn-primary btn-block mt-2"><i class="fas fa-download"></i> 下载客户端</a>
                <a href="/user/downloads" class="btn btn-light btn-block mt-2"><i class="fas fa-book"></i> 查看下载和教程</a>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h4 style="color:#6777ef">订阅链接</h4></div>
            <div class="card-body">
                <p class="text-muted" style="font-size:12px;margin-bottom:12px">支持一键导入的直接点「导入」；其余点「复制订阅链接」后，在客户端里手动添加订阅。</p>
                @foreach($clients as $c)
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span style="font-size:13px"><i class="{{ $c['icon'] }}" style="color:#6777ef;width:18px"></i> {{ $c['name'] }}</span>
                    <span class="text-nowrap">
                        @if($c['qr_target'] === 'scheme')
                            <a href="{{ $c['scheme'] }}" data-turbo="false" rel="nofollow" class="btn btn-primary btn-sm"><i class="fas fa-bolt"></i> 一键导入</a>
                        @endif
                        <button class="btn btn-outline-primary btn-sm" onclick="copySub('{{ $c['url'] }}')"><i class="fas fa-copy"></i> 复制</button>
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
