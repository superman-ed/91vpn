@extends('layouts.guest')
@section('title', '注册')
@section('content')
<div style="text-align:center; padding: 6px 4px 4px">
    <div style="width:64px;height:64px;border-radius:6px;margin:0 auto 18px;overflow:hidden;border:1px solid #C4BCA8;background:#F1EEE4">
        <img src="/og.jpg" alt="91VPN" style="width:100%;height:100%;object-fit:cover;display:block">
    </div>
    <h4 style="color:#1A160F;margin-bottom:12px;font-weight:800">请在客户端中注册</h4>
    <div style="font-size:14px;color:#5E5849;line-height:1.9">
        暂时不支持在网站上注册账户,<br>请您在客户端中进行注册。<br>
        如果没有客户端,请返回官网首页下载。
    </div>
    <a href="/" class="btn btn-auth btn-block" style="margin-top:22px"><i class="fas fa-download"></i> 返回首页下载客户端</a>
    <div class="auth-links" style="margin-top:14px">已有账号？<a href="/login">点击登录</a></div>
</div>
@endsection
