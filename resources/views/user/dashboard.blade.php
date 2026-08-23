@extends('layouts.user')
@section('title', '首页')
@section('content')
<div class="cards">
    <div class="stat">
        <div class="icon primary">👑</div>
        <div class="wrap">
            <h4>会员等级</h4>
            <div class="num">{{ $className }}</div>
            <div class="sub">@if($user->class > 0 && $user->class_expire){{ $user->class_expire->format('Y-m-d') }} 到期 @else 未开通套餐 @endif</div>
        </div>
    </div>
    <div class="stat">
        <div class="icon success">📶</div>
        <div class="wrap">
            <h4>剩余流量</h4>
            <div class="num">{{ number_format($remainGb, 2) }} <small>GB</small></div>
            <div class="sub">今日 {{ number_format($todayGb, 2) }}G / 共 {{ number_format($totalGb, 2) }}G</div>
        </div>
    </div>
    <div class="stat">
        <div class="icon warning">💰</div>
        <div class="wrap">
            <h4>钱包余额</h4>
            <div class="num"><small>¥</small> {{ number_format($user->money, 2) }}</div>
            <div class="sub"><a href="/user/wallet">充值 / 购买套餐</a></div>
        </div>
    </div>
    <div class="stat">
        <div class="icon info">📱</div>
        <div class="wrap">
            <h4>设备上限</h4>
            <div class="num">{{ $user->node_ip_limit ?: '∞' }}</div>
            <div class="sub">限速 {{ $user->node_speed_limit ? $user->node_speed_limit.' Mbps' : '不限' }}</div>
        </div>
    </div>
</div>

<div class="panel">
    <h3>每日签到</h3>
    <form method="POST" action="/user/checkin">@csrf
        <button class="btn">签到领流量</button>
    </form>
</div>

<div class="panel">
    <h3>近7天流量</h3>
    @php $max = max(0.01, $chart->max('gb')); @endphp
    <div style="display:flex;align-items:flex-end;gap:12px;height:160px;padding-top:10px">
        @foreach($chart as $c)
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%">
            <div style="font-size:11px;color:#6c757d;margin-bottom:4px">{{ $c['gb'] }}G</div>
            <div style="width:70%;background:#6777ef;border-radius:4px 4px 0 0;height:{{ max(2, ($c['gb']/$max)*120) }}px"></div>
            <div style="font-size:11px;color:#acb5c9;margin-top:6px">{{ $c['date'] }}</div>
        </div>
        @endforeach
    </div>
</div>

<div class="panel">
    <h3>我的订阅</h3>
    <p style="font-size:14px;color:#6c757d">订阅链接在「<a href="/user/node">节点设置</a>」查看和导入客户端。</p>
</div>
@endsection
