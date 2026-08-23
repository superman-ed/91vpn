@extends('layouts.user')
@section('title', '首页')
@section('head')
<style>
.stat-row > [class*="col-"] { display: flex; margin-bottom: 30px; }
.stat-row .card-statistic-1 { height: 100%; min-height: 130px; margin-bottom: 0; }
</style>
@endsection
@section('content')
<div class="row stat-row">
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
            <div class="card-icon bg-primary"><i class="fas fa-clock"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4>会员时长</h4></div>
                <div class="card-body">
                    {{ $membership }}
                    <small class="text-muted d-block" style="font-weight:400;font-size:11px;white-space:nowrap">{{ $className }}@if($expireDate) · {{ $expireDate }} 到期@endif</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
            <div class="card-icon bg-success"><i class="fas fa-signal"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4>剩余流量</h4></div>
                <div class="card-body">
                    {{ number_format($remainGb, 1) }} GB
                    <div class="progress mt-2" style="height:6px">
                        @php $barColor = $usagePercent >= 90 ? 'bg-danger' : ($usagePercent >= 70 ? 'bg-warning' : 'bg-success'); @endphp
                        <div class="progress-bar {{ $barColor }}" role="progressbar" style="width:{{ $usagePercent }}%"></div>
                    </div>
                    <small class="text-muted d-block" style="font-weight:400;font-size:11px;white-space:nowrap">已用 {{ $usagePercent }}%</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
            <div class="card-icon bg-warning"><i class="fas fa-wallet"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4>钱包余额</h4></div>
                <div class="card-body">
                    ¥{{ number_format($user->money, 2) }}
                    <small class="text-muted d-block" style="font-weight:400;font-size:11px;white-space:nowrap">累计获得返利 {{ number_format($rebateTotal, 2) }} 元</small>
                    <small class="d-block" style="font-size:11px;white-space:nowrap;font-weight:400">
                        <a href="{{ route('user.wallet') }}">我的钱包</a> | <a href="{{ route('user.shop') }}">购买套餐</a>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
            <div class="card-icon bg-info"><i class="fas fa-mobile-alt"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4>设备上限</h4></div>
                <div class="card-body">{{ $user->node_ip_limit ?: '∞' }}</div>
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
