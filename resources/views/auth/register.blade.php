@extends('layouts.guest')
@section('title', '注册')
@section('content')
<div style="text-align:center; padding: 6px 4px 4px">
    <div style="width:64px;height:64px;border-radius:18px;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#c6ffbf,#feffa5)">
        <i class="fas fa-mobile-screen-button" style="font-size:28px;color:#3aa14a"></i>
    </div>
    <h4 style="color:#34395e;margin-bottom:12px">请在客户端中注册</h4>
    <div style="font-size:14px;color:#7a869a;line-height:1.9">
        暂时不支持在网站上注册账户,<br>请您在客户端中进行注册。<br>
        如果没有客户端,请返回官网首页下载。
    </div>
    <a href="/" class="btn btn-auth btn-block" style="margin-top:22px"><i class="fas fa-download"></i> 返回首页下载客户端</a>
    <div class="auth-links" style="margin-top:14px">已有账号？<a href="/login">点击登录</a></div>
</div>
@endsection
