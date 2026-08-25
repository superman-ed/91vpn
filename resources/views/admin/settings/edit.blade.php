@extends('layouts.admin')
@section('title', '站点设置')
@section('content')
<div class="adm-head"><h4><i class="fas fa-cog text-primary"></i> 站点设置</h4></div>

<form method="POST" action="/admin/settings" class="adm-form">@csrf @method('PUT')

    <div class="card adm-form-card">
        <div class="card-header"><span class="ic"><i class="fas fa-circle-info"></i></span><h4>购买须知</h4></div>
        <div class="card-body">
            <p class="form-tip">每行一条，显示在收银台底部；留空则恢复内置默认文案。</p>
            <textarea name="buy_notice" rows="5" class="form-control">{{ old('buy_notice', $buyNotice) }}</textarea>
        </div>
    </div>

    <div class="card adm-form-card">
        <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#63c76a,#3fae57)"><i class="fas fa-credit-card"></i></span><h4>支付网关（易支付）</h4></div>
        <div class="card-body">
            <p class="form-tip">三项填齐后，收银台的支付宝/微信/USDT 将跳转网关支付并由异步回调发货；留空则为模拟支付（开发用）。异步通知地址：<code>{{ url('/pay/epay/notify') }}</code></p>
            <div class="row">
                <div class="form-group col-md-6"><label>网关地址</label><input name="epay_url" value="{{ old('epay_url', $epayUrl) }}" class="form-control" placeholder="https://pay.example.com"></div>
                <div class="form-group col-md-3"><label>商户 PID</label><input name="epay_pid" value="{{ old('epay_pid', $epayPid) }}" class="form-control"></div>
                <div class="form-group col-md-3"><label>商户密钥 KEY</label><input name="epay_key" value="{{ old('epay_key', $epayKey) }}" class="form-control"></div>
            </div>
        </div>
    </div>

    <div class="card adm-form-card">
        <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#ffb020,#ff9f1a)"><i class="fas fa-gift"></i></span><h4>邀请返利</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-6"><label>充值返利比例（%）</label><input name="rebate_rate" type="number" step="0.1" value="{{ old('rebate_rate', $rebateRate) }}" class="form-control"><small class="text-muted">下线每次充值，邀请人获得该比例返利。</small></div>
                <div class="form-group col-md-6"><label>受邀注册奖励（元）</label><input name="signup_bonus" type="number" step="0.01" value="{{ old('signup_bonus', $signupBonus) }}" class="form-control"><small class="text-muted">通过邀请码注册的新用户获得的初始资金。</small></div>
            </div>
        </div>
    </div>

    <div class="card adm-form-card">
        <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#7c4ddb,#6636c0)"><i class="fas fa-envelope"></i></span><h4>邮件发送（SMTP）</h4></div>
        <div class="card-body">
            <p class="form-tip">配置后注册/找回密码的邮箱验证码将真实发送；留空则仅记录到日志（开发用）。465→SSL，587→TLS。</p>
            <div class="row">
                <div class="form-group col-md-6"><label>SMTP 服务器</label><input name="smtp_host" value="{{ old('smtp_host', $smtpHost) }}" class="form-control" placeholder="smtp.exmail.qq.com"></div>
                <div class="form-group col-md-3"><label>端口</label><input name="smtp_port" type="number" value="{{ old('smtp_port', $smtpPort) }}" class="form-control"></div>
                <div class="form-group col-md-3"><label>加密</label><select name="smtp_encryption" class="form-control">
                    @foreach(['ssl' => 'SSL', 'tls' => 'TLS', 'none' => '不加密'] as $k => $v)<option value="{{ $k }}" @selected(old('smtp_encryption', $smtpEncryption ?: 'none') === $k)>{{ $v }}</option>@endforeach
                </select></div>
                <div class="form-group col-md-6"><label>用户名（发信邮箱）</label><input name="smtp_username" value="{{ old('smtp_username', $smtpUsername) }}" class="form-control" placeholder="noreply@yourdomain.com"></div>
                <div class="form-group col-md-6"><label>密码 / 授权码</label><input name="smtp_password" type="password" value="{{ old('smtp_password', $smtpPassword) }}" class="form-control" placeholder="邮箱 SMTP 授权码"></div>
                <div class="form-group col-md-6"><label>发件人地址（选填，默认同用户名）</label><input name="smtp_from" value="{{ old('smtp_from', $smtpFrom) }}" class="form-control"></div>
                <div class="form-group col-md-6"><label>发件人名称</label><input name="smtp_from_name" value="{{ old('smtp_from_name', $smtpFromName) }}" class="form-control"></div>
            </div>
        </div>
    </div>

    <button class="btn adm-btn btn-lg"><i class="fas fa-save"></i> 保存设置</button>
</form>
@endsection
