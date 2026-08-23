@extends('layouts.user')
@section('title', '节点设置')
@section('content')
<div class="card">
    <div class="card-header"><h4>一键导入 / 扫码导入</h4></div>
    <div class="card-body">
        <p class="text-muted">点按钮直接唤起客户端导入，或用对应 App 扫二维码（需已安装客户端）。</p>
        <div class="row">
            @foreach($clients as $c)
            <div class="col-6 col-md-3 mb-3">
                <div class="text-center p-3" style="border:1px solid #eee;border-radius:8px;height:100%">
                    <div class="font-weight-bold mb-2"><i class="{{ $c['icon'] }}" style="color:#6777ef"></i> {{ $c['name'] }}</div>
                    <img src="{{ $c['qr'] }}" alt="{{ $c['name'] }} 订阅二维码" style="width:150px;height:150px;max-width:100%;background:#fff;border:1px solid #f0f0f0;border-radius:6px;padding:6px">
                    <div class="mt-2">
                        <a href="{{ $c['scheme'] }}" class="btn btn-primary btn-sm btn-block"><i class="fas fa-bolt"></i> 一键导入</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <button class="btn btn-outline-primary" onclick="navigator.clipboard.writeText('{{ $subUrl }}');this.innerHTML='<i class=\'fas fa-check\'></i> 已复制'"><i class="fas fa-copy"></i> 复制通用订阅链接</button>
    </div>
</div>

<div class="card">
    <div class="card-header"><h4>订阅链接</h4></div>
    <div class="card-body">
        <div class="alert alert-light" style="word-break:break-all"><code>{{ $subUrl }}</code></div>
        <form method="POST" action="/user/node/reset-sub" class="d-inline" onsubmit="return confirm('重置后旧链接立即失效，确认？')">@csrf<button class="btn btn-danger">重置订阅链接</button></form>
        <small class="text-muted d-block mt-2">重置后需在客户端重新导入，旧链接立即失效。</small>
    </div>
</div>

<div class="card">
    <div class="card-header"><h4>连接密码</h4></div>
    <div class="card-body">
        <p class="text-muted">当前连接密码：<code>{{ $user->passwd }}</code>。⚠️ 重置会同时变更 UUID，需重新导入订阅。</p>
        <form method="POST" action="/user/node/reset-passwd" onsubmit="return confirm('重置会同时变更 UUID，确认？')">@csrf<button class="btn btn-primary">随机重置连接密码</button></form>
    </div>
</div>
@endsection
