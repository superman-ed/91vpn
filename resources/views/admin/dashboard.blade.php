@extends('layouts.admin')
@section('title', '概览')
@section('content')
<div class="row">
    <div class="col-lg-3 col-md-6 col-6"><div class="card card-statistic-1"><div class="card-icon bg-primary"><i class="fas fa-users"></i></div><div class="card-wrap"><div class="card-header"><h4>用户总数</h4></div><div class="card-body">{{ $userCount }}</div></div></div></div>
    <div class="col-lg-3 col-md-6 col-6"><div class="card card-statistic-1"><div class="card-icon bg-info"><i class="fas fa-server"></i></div><div class="card-wrap"><div class="card-header"><h4>节点数</h4></div><div class="card-body">{{ $nodeCount }}</div></div></div></div>
    <div class="col-lg-3 col-md-6 col-6"><div class="card card-statistic-1"><div class="card-icon bg-warning"><i class="fas fa-box"></i></div><div class="card-wrap"><div class="card-header"><h4>套餐数</h4></div><div class="card-body">{{ $planCount }}</div></div></div></div>
    <div class="col-lg-3 col-md-6 col-6"><div class="card card-statistic-1"><div class="card-icon bg-success"><i class="fas fa-yen-sign"></i></div><div class="card-wrap"><div class="card-header"><h4>总收入</h4></div><div class="card-body">¥{{ number_format($revenue,2) }}</div></div></div></div>
</div>
<div class="card"><div class="card-header"><h4>快捷操作</h4></div><div class="card-body">
    <a href="/admin/nodes/create" class="btn btn-primary">添加节点</a>
    <a href="/admin/plans/create" class="btn btn-primary">添加套餐</a>
    <a href="/admin/coupons/create" class="btn btn-primary">生成优惠券</a>
    <a href="/admin/announcements/create" class="btn btn-primary">发布公告</a>
    <span class="ml-3 text-muted">已支付订单：{{ $paidOrders }}</span>
</div></div>
@endsection
