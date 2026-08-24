@extends('layouts.admin')
@section('title', '站点设置')
@section('content')
<div class="card"><div class="card-body">
<form method="POST" action="/admin/settings">@csrf @method('PUT')
    <div class="form-group">
        <label>购买须知（每行一条，显示在收银台底部）</label>
        <textarea name="buy_notice" rows="6" class="form-control">{{ old('buy_notice', $buyNotice) }}</textarea>
        <small class="text-muted">留空则恢复内置默认文案。</small>
    </div>
    <hr>
    <h5>支付网关（易支付）</h5>
    <p class="text-muted">三项填齐后，收银台的支付宝/微信/USDT 将跳转网关支付并由异步回调发货；留空则为模拟支付（开发用）。异步通知地址：<code>{{ url('/pay/epay/notify') }}</code></p>
    <div class="row">
        <div class="form-group col-md-6"><label>网关地址</label><input name="epay_url" value="{{ old('epay_url', $epayUrl) }}" class="form-control" placeholder="https://pay.example.com"></div>
        <div class="form-group col-md-3"><label>商户 PID</label><input name="epay_pid" value="{{ old('epay_pid', $epayPid) }}" class="form-control"></div>
        <div class="form-group col-md-3"><label>商户密钥 KEY</label><input name="epay_key" value="{{ old('epay_key', $epayKey) }}" class="form-control"></div>
    </div>
    <hr>
    <h5>邀请返利</h5>
    <div class="row">
        <div class="form-group col-md-6"><label>充值返利比例（%）</label><input name="rebate_rate" type="number" step="0.1" value="{{ old('rebate_rate', $rebateRate) }}" class="form-control"><small class="text-muted">下线每次充值，邀请人获得该比例返利。</small></div>
        <div class="form-group col-md-6"><label>受邀注册奖励（元）</label><input name="signup_bonus" type="number" step="0.01" value="{{ old('signup_bonus', $signupBonus) }}" class="form-control"><small class="text-muted">通过邀请码注册的新用户获得的初始资金。</small></div>
    </div>
    <button class="btn btn-primary">保存</button>
</form>
</div></div>
@endsection
