@extends('layouts.admin')
@section('title', '站点设置')
@section('content')
<style>
.set-tabs { display: flex; gap: 4px; background: #f1f3fb; padding: 5px; border-radius: 11px; margin-bottom: 20px; flex-wrap: wrap; }
.set-tabs button { border: none; background: transparent; color: #7a869a; font-weight: 600; font-size: 13.5px; padding: 9px 18px; border-radius: 8px; cursor: pointer; transition: all .15s; }
.set-tabs button:hover { color: #6777ef; }
.set-tabs button.active { background: #fff; color: #6777ef; box-shadow: 0 2px 8px rgba(103,119,239,.15); }
.set-pane { display: none; }
.set-pane.active { display: block; }
</style>
<div class="adm-head"><h4><i class="fas fa-cog text-primary"></i> 站点设置</h4></div>

<div class="set-tabs">
    <button type="button" class="active" data-tab="notice"><i class="fas fa-info-circle"></i> 购买须知</button>
    <button type="button" data-tab="pay"><i class="fas fa-credit-card"></i> 支付网关</button>
    <button type="button" data-tab="rebate"><i class="fas fa-gift"></i> 邀请返利</button>
    <button type="button" data-tab="mail"><i class="fas fa-envelope"></i> 邮件发送</button>
    <button type="button" data-tab="support"><i class="fas fa-headset"></i> 在线客服</button>
</div>

<form method="POST" action="/admin/settings" class="adm-form">@csrf @method('PUT')

    <div class="set-pane active" data-pane="notice">
        <div class="card adm-form-card">
            <div class="card-header"><span class="ic"><i class="fas fa-info-circle"></i></span><h4>购买须知</h4></div>
            <div class="card-body">
                <p class="form-tip">每行一条，显示在收银台底部；留空则恢复内置默认文案。</p>
                <textarea name="buy_notice" rows="6" class="form-control">{{ old('buy_notice', $buyNotice) }}</textarea>
            </div>
        </div>
    </div>

    <div class="set-pane" data-pane="pay">
        <div class="card adm-form-card">
            <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#63c76a,#3fae57)"><i class="fas fa-credit-card"></i></span><h4>支付网关（易支付）</h4></div>
            <div class="card-body">
                <p class="form-tip">三项填齐后，收银台的支付宝/微信/USDT 将跳转网关支付并由异步回调发货；留空则为模拟支付（开发用）。异步通知地址：<code>{{ url('/pay/epay/notify') }}</code></p>
                <div class="row">
                    <div class="form-group col-md-6"><label>网关地址</label><input name="epay_url" value="{{ old('epay_url', $epayUrl) }}" class="form-control" placeholder="https://pay.example.com"></div>
                    <div class="form-group col-md-3"><label>商户 PID</label><input name="epay_pid" value="{{ old('epay_pid', $epayPid) }}" class="form-control"></div>
                    <div class="form-group col-md-3"><label>商户密钥 KEY</label><input name="epay_key" value="{{ old('epay_key', $epayKey) }}" class="form-control"></div>
                </div>
                <button form="testGatewayForm" type="submit" class="btn btn-light" style="border-radius:9px"><i class="fas fa-plug text-primary"></i> 检测网关连通性</button>
                <small class="form-tip d-inline ml-2">先「保存设置」再检测。仅探地址可访问,不发起真实交易。</small>
            </div>
        </div>
    </div>

    <div class="set-pane" data-pane="rebate">
        <div class="card adm-form-card">
            <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#ffb020,#ff9f1a)"><i class="fas fa-gift"></i></span><h4>邀请返利</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="form-group col-md-6"><label>充值返利比例（%）</label><input name="rebate_rate" type="number" step="0.1" value="{{ old('rebate_rate', $rebateRate) }}" class="form-control"><small class="text-muted">下线每次充值，邀请人获得该比例返利。</small></div>
                    <div class="form-group col-md-6"><label>受邀注册奖励（元）</label><input name="signup_bonus" type="number" step="0.01" value="{{ old('signup_bonus', $signupBonus) }}" class="form-control"><small class="text-muted">通过邀请码注册的新用户获得的初始资金。</small></div>
                </div>
            </div>
        </div>
    </div>

    <div class="set-pane" data-pane="mail">
        <div class="card adm-form-card">
            <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#7c4ddb,#6636c0)"><i class="fas fa-envelope"></i></span><h4>邮件发送（SMTP）</h4></div>
            <div class="card-body">
                <p class="form-tip">配置后注册/找回密码的邮箱验证码将真实发送；留空则仅记录到日志（开发用）。465→SSL，587→TLS。<b>推荐阿里云邮件推送（DirectMail）</b>，对国内邮箱送达友好，参数见下方示例。</p>
                <div class="row">
                    <div class="form-group col-md-6"><label>SMTP 服务器</label><input name="smtp_host" value="{{ old('smtp_host', $smtpHost) }}" class="form-control" placeholder="smtpdm.aliyun.com"></div>
                    <div class="form-group col-md-3"><label>端口</label><input name="smtp_port" type="number" value="{{ old('smtp_port', $smtpPort) }}" class="form-control" placeholder="465"></div>
                    <div class="form-group col-md-3"><label>加密</label><select name="smtp_encryption" class="form-control">
                        @foreach(['ssl' => 'SSL', 'tls' => 'TLS', 'none' => '不加密'] as $k => $v)<option value="{{ $k }}" @selected(old('smtp_encryption', $smtpEncryption ?: 'none') === $k)>{{ $v }}</option>@endforeach
                    </select></div>
                    <div class="form-group col-md-6"><label>用户名（发信地址）</label><input name="smtp_username" value="{{ old('smtp_username', $smtpUsername) }}" class="form-control" placeholder="noreply@mail.你的域名.com"></div>
                    <div class="form-group col-md-6"><label>密码（阿里云发信地址的 SMTP 密码）</label><input name="smtp_password" type="password" value="{{ old('smtp_password', $smtpPassword) }}" class="form-control" placeholder="发信地址的 SMTP 密码（非阿里云账号密码）"></div>
                    <div class="form-group col-md-6"><label>发件人地址（选填，默认同用户名）</label><input name="smtp_from" value="{{ old('smtp_from', $smtpFrom) }}" class="form-control"></div>
                    <div class="form-group col-md-6"><label>发件人名称</label><input name="smtp_from_name" value="{{ old('smtp_from_name', $smtpFromName) }}" class="form-control"></div>
                </div>
                <div class="row">
                    <div class="form-group col-md-8 mb-0">
                        <label>发送测试邮件到</label>
                        <div class="input-group">
                            <input form="testEmailForm" name="test_email" type="email" value="{{ auth()->user()->email }}" class="form-control" placeholder="收件邮箱">
                            <div class="input-group-append"><button form="testEmailForm" type="submit" class="btn btn-light" style="border-radius:0 9px 9px 0"><i class="fas fa-paper-plane text-primary"></i> 发送测试</button></div>
                        </div>
                        <small class="form-tip">先「保存设置」写入 SMTP 配置,再发送测试邮件验证连通性。</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="set-pane" data-pane="support">
        <div class="card adm-form-card">
            <div class="card-header"><span class="ic" style="background:linear-gradient(135deg,#3aa0c7,#2a86ab)"><i class="fas fa-headset"></i></span><h4>在线客服</h4></div>
            <div class="card-body">
                <p class="form-tip">用户端右下角悬浮客服。优先级：<b>Crisp ID</b> ＞ 第三方代码 ＞ 自建面板。三者都不填则显示自建面板（Telegram / 客服群 / 工单）。</p>
                <div class="row">
                    <div class="form-group col-md-6"><label>Telegram 客服链接</label><input name="support_tg" value="{{ old('support_tg', $supportTg) }}" class="form-control" placeholder="https://t.me/your_support"><small class="text-muted">留空则面板不显示该入口。</small></div>
                    <div class="form-group col-md-6"><label>客服群 / 交流群链接</label><input name="support_group" value="{{ old('support_group', $supportGroup) }}" class="form-control" placeholder="https://t.me/your_group"></div>
                    <div class="form-group col-md-12"><label>在线时段（选填）</label><input name="support_hours" value="{{ old('support_hours', $supportHours) }}" class="form-control" placeholder="例：每日 10:00 - 24:00 在线，其余时段请提交工单"></div>
                </div>
                <hr style="margin:6px 0 18px;border-color:#eef1f8">
                <div class="row">
                    <div class="form-group col-md-12"><label>Crisp Website ID（推荐）</label><input name="crisp_website_id" value="{{ old('crisp_website_id', $crispWebsiteId) }}" class="form-control" style="font-family:SFMono-Regular,Menlo,Consolas,monospace" placeholder="233710e4-9a5f-4b81-be1e-a1cb6fe17a62"><small class="text-muted">填入 Crisp 后台的 Website ID（36 位）即自动加载 Crisp，无需粘贴代码。</small></div>
                    <div class="form-group col-md-12">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin:0">
                            <input type="checkbox" name="crisp_bind_identity" value="1" @checked(old('crisp_bind_identity', $crispBindIdentity)) style="width:17px;height:17px">
                            <span>透传登录用户身份给客服（邮箱 / 昵称）</span>
                        </label>
                        <small class="text-muted d-block mt-1">开：客服能认出咨询者是哪个用户，体验好，但每个登录用户会占用一个 Crisp「客户档案」——<b>免费版仅 100 个且已识别档案不自动回收</b>，放量运营会占满。<br>关（默认）：匿名咨询，Crisp 自动回收不活跃访客档案，100 额度滚动使用，免费版可长期使用。</small>
                    </div>
                    <div class="form-group col-md-12"><label>其它第三方客服代码（选填）</label><textarea name="support_widget" rows="4" class="form-control" style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px" placeholder="非 Crisp（如 Tawk.to / 美洽）时，整段粘贴其官方 &lt;script&gt; 代码。已填 Crisp ID 时本项忽略。">{{ old('support_widget', $supportWidget) }}</textarea><small class="text-muted">⚠️ 代码会原样注入用户端页面，请只粘贴可信来源的官方客服代码。</small></div>
                </div>
            </div>
        </div>
    </div>

    <button class="btn adm-btn btn-lg"><i class="fas fa-save"></i> 保存设置</button>
</form>

{{-- 自检工具:独立表单,由上方带 form= 属性的按钮触发,不与主设置表单混用 --}}
<form id="testEmailForm" method="POST" action="/admin/settings/test-email" class="d-none">@csrf</form>
<form id="testGatewayForm" method="POST" action="/admin/settings/test-gateway" class="d-none">@csrf</form>
<script>
(function () {
    var KEY = 'admin_settings_tab';
    function show(tab) {
        document.querySelectorAll('.set-tabs button').forEach(function (b) { b.classList.toggle('active', b.dataset.tab === tab); });
        document.querySelectorAll('.set-pane').forEach(function (p) { p.classList.toggle('active', p.dataset.pane === tab); });
        try { sessionStorage.setItem(KEY, tab); } catch (e) {}
    }
    document.querySelectorAll('.set-tabs button').forEach(function (b) {
        b.addEventListener('click', function () { show(b.dataset.tab); });
    });
    var saved; try { saved = sessionStorage.getItem(KEY); } catch (e) {}
    if (saved && document.querySelector('[data-pane="' + saved + '"]')) { show(saved); }
})();
</script>
@endsection
