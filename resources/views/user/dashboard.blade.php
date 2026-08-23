@extends('layouts.user')
@section('title', '首页')
@section('head')
<style>
.stat-row > [class*="col-"] { display: flex; margin-bottom: 30px; }
.stat-row .stat-card { width: 100%; height: 100%; margin-bottom: 0; }
.stat-card .card-body { padding: 18px 20px; }
.stat-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.stat-head .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 22px; flex-shrink: 0; }
.stat-head .stat-title { margin: 0; font-size: 15px; font-weight: 600; color: #6c757d; }
.stat-value { font-size: 30px; font-weight: 700; color: #34395e; line-height: 1.2; }
.stat-sub-box { background: #f4f6f9; border-radius: 10px; padding: 10px 14px; margin-top: 12px; }
.stat-sub-box .stat-sub { display: block; font-size: 13px; font-weight: 400; color: #6c757d; white-space: nowrap; }
.stat-sub-box .stat-sub + .stat-sub { margin-top: 4px; }
.stat-sub-box a { color: #6777ef; }
</style>
@endsection
@section('content')
<div class="row stat-row">
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-head">
                    <span class="stat-icon bg-primary"><i class="fas fa-clock"></i></span>
                    <h4 class="stat-title">会员时长</h4>
                </div>
                <div class="stat-value">{{ $membership }}</div>
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
                <div class="stat-value">{{ number_format($remainGb, 1) }} GB</div>
                <div class="stat-sub-box">
                    @php $barColor = $usagePercent >= 90 ? 'bg-danger' : ($usagePercent >= 70 ? 'bg-warning' : 'bg-success'); @endphp
                    <div class="progress" style="height:6px;margin-bottom:6px">
                        <div class="progress-bar {{ $barColor }}" role="progressbar" style="width:{{ $usagePercent }}%"></div>
                    </div>
                    <span class="stat-sub">已用 {{ $usagePercent }}%</span>
                </div>
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
                <div class="stat-value">¥{{ number_format($user->money, 2) }}</div>
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
                <div class="stat-value">{{ $user->node_ip_limit ?: '∞' }}</div>
                <div class="stat-sub-box">
                    <span class="stat-sub">同时在线设备数上限</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header"><h4>近7天流量</h4></div>
            <div class="card-body">
                @php $max = max(0.01, $chart->max('gb')); @endphp
                <div style="display:flex;align-items:flex-end;gap:14px;height:180px">
                    @foreach($chart as $c)
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%">
                        <div class="text-muted" style="font-size:12px;margin-bottom:6px">{{ $c['gb'] }}G</div>
                        <div style="width:60%;background:#6777ef;border-radius:4px 4px 0 0;height:{{ max(3, ($c['gb']/$max)*130) }}px"></div>
                        <div class="text-muted" style="font-size:12px;margin-top:8px">{{ $c['date'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><h4>账户信息</h4></div>
            <div class="card-body">
                <ul class="list-unstyled list-unstyled-border">
                    <li class="media"><div class="media-body"><div class="text-small text-muted">到期时间</div>
                        {{ $user->class > 0 && $user->class_expire ? $user->class_expire->format('Y-m-d H:i') : '未开通套餐' }}</div></li>
                    <li class="media"><div class="media-body"><div class="text-small text-muted">今日已用</div>{{ number_format($todayGb, 2) }} GB</div></li>
                    <li class="media"><div class="media-body"><div class="text-small text-muted">总流量</div>{{ number_format($totalGb, 2) }} GB</div></li>
                    <li class="media"><div class="media-body"><div class="text-small text-muted">端口限速</div>{{ $user->node_speed_limit ? $user->node_speed_limit.' Mbps' : '不限速' }}</div></li>
                </ul>
                <form method="POST" action="/user/checkin">@csrf
                    @if($checkedIn)
                        <button class="btn btn-light btn-block" disabled><i class="fas fa-check"></i> 今日已签到</button>
                    @else
                        <button class="btn btn-primary btn-block"><i class="fas fa-calendar-check"></i> 每日签到领流量</button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h4>快速导入</h4></div>
    <div class="card-body">
        <a href="{{ $clashScheme }}" data-turbo="false" rel="nofollow" class="btn btn-primary mb-2"><i class="fas fa-bolt"></i> 一键导入 Clash</a>
        <button class="btn btn-outline-primary mb-2" onclick="copySub('{{ $subUrl }}')"><i class="fas fa-copy"></i> 复制订阅链接</button>
        <a href="/user/downloads" class="btn btn-light mb-2"><i class="fas fa-download"></i> 客户端下载</a>
    </div>
</div>
@endsection
