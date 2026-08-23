@extends('layouts.user')
@section('title', '客户端下载')
@section('content')
<div class="card"><div class="card-header"><h4>客户端下载</h4></div>
<div class="card-body">
    <p class="text-muted">下载对应平台的客户端，安装后在「<a href="/user/node">节点设置</a>」一键导入订阅即可使用。</p>
    <div class="row">
        @foreach($clients as $c)
        <div class="col-6 col-md-3 mb-3">
            <div class="text-center p-3" style="border:1px solid #eee;border-radius:8px">
                <i class="{{ $c['icon'] }}" style="font-size:38px;color:#6777ef"></i>
                <div class="mt-2 font-weight-bold">{{ $c['os'] }}</div>
                <div class="text-muted mb-2" style="font-size:13px">{{ $c['name'] }}</div>
                <a href="{{ $c['url'] }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">下载</a>
            </div>
        </div>
        @endforeach
    </div>
</div></div>
<div class="card"><div class="card-header"><h4>使用步骤</h4></div><div class="card-body">
<ol style="line-height:2">
    <li>下载并安装对应平台客户端</li>
    <li>去「节点设置」复制订阅链接，或点「一键导入」</li>
    <li>客户端里更新订阅、选择节点、开启代理</li>
    <li>手机可在「节点设置」扫二维码快速导入</li>
</ol>
</div></div>
@endsection
