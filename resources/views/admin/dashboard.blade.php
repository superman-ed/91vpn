@extends('layouts.admin')
@section('title', '概览')
@section('content')
<div class="cards">
    <div class="card"><div class="k">用户总数</div><div class="v">{{ $userCount }}</div></div>
    <div class="card"><div class="k">节点数</div><div class="v">{{ $nodeCount }}</div></div>
    <div class="card"><div class="k">套餐数</div><div class="v">{{ $planCount }}</div></div>
    <div class="card"><div class="k">已支付订单</div><div class="v">{{ $paidOrders }}</div></div>
    <div class="card"><div class="k">总收入</div><div class="v"><small>¥</small>{{ number_format($revenue, 2) }}</div></div>
</div>
<div class="panel"><h3>快捷操作</h3>
    <a href="/admin/nodes/create" class="btn">添加节点</a>
    <a href="/admin/plans/create" class="btn">添加套餐</a>
    <a href="/admin/announcements/create" class="btn">发布公告</a>
</div>
@endsection
