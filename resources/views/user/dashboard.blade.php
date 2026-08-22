@extends('layouts.user')
@section('title', '首页')
@section('content')
<div class="cards">
    <div class="card">
        <div class="k">会员等级</div>
        <div class="v">{{ $className }}</div>
        <div class="sub">
            @if($user->class > 0 && $user->class_expire)
                {{ $user->class_expire->format('Y-m-d H:i') }} 到期
            @else
                未开通套餐
            @endif
        </div>
    </div>
    <div class="card">
        <div class="k">剩余流量</div>
        <div class="v">{{ number_format($remainGb, 2) }} <small>GB</small></div>
        <div class="sub">今日已用 {{ number_format($todayGb, 2) }} GB / 共 {{ number_format($totalGb, 2) }} GB</div>
    </div>
    <div class="card">
        <div class="k">钱包余额</div>
        <div class="v"><small>¥</small> {{ number_format($user->money, 2) }}</div>
        <div class="sub"><a href="/user/wallet">充值 / 购买套餐</a></div>
    </div>
    <div class="card">
        <div class="k">在线设备上限</div>
        <div class="v">{{ $user->node_ip_limit ?: '∞' }}</div>
        <div class="sub">限速 {{ $user->node_speed_limit ?: '不限' }} @if($user->node_speed_limit) Mbps @endif</div>
    </div>
</div>

<div class="panel">
    <h3>每日签到</h3>
    <form method="POST" action="/user/checkin">@csrf
        <button class="btn">签到领流量</button>
    </form>
</div>

<div class="panel">
    <h3>我的订阅</h3>
    <p style="font-size:14px;color:#6c757d">订阅链接在「<a href="/user/node">节点设置</a>」查看和导入客户端。</p>
</div>
@endsection
