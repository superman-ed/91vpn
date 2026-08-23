@extends('layouts.admin')
@section('title', '概览')
@section('content')
<div class="cards">
    <div class="stat"><div class="icon primary">👥</div><div class="wrap"><h4>用户总数</h4><div class="num">{{ $userCount }}</div></div></div>
    <div class="stat"><div class="icon info">🖧</div><div class="wrap"><h4>节点数</h4><div class="num">{{ $nodeCount }}</div></div></div>
    <div class="stat"><div class="icon warning">📦</div><div class="wrap"><h4>套餐数</h4><div class="num">{{ $planCount }}</div></div></div>
    <div class="stat"><div class="icon success">🧾</div><div class="wrap"><h4>已支付订单</h4><div class="num">{{ $paidOrders }}</div></div></div>
    <div class="stat"><div class="icon danger">💵</div><div class="wrap"><h4>总收入</h4><div class="num"><small>¥</small>{{ number_format($revenue, 2) }}</div></div></div>
</div>
<div class="panel"><h3>快捷操作</h3>
    <div class="body" style="padding-top:16px">
        <a href="/admin/nodes/create" class="btn">添加节点</a>
        <a href="/admin/plans/create" class="btn">添加套餐</a>
        <a href="/admin/coupons/create" class="btn">生成优惠券</a>
        <a href="/admin/announcements/create" class="btn">发布公告</a>
    </div>
</div>
@endsection
