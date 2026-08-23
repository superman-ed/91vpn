@extends('layouts.user')
@section('title', '节点设置')
@section('content')
<div class="card">
    <div class="card-header"><h4>一键导入客户端</h4></div>
    <div class="card-body">
        <p class="text-muted">点击按钮直接把订阅导入对应客户端（需已安装）。</p>
        <a href="{{ $clashScheme }}" class="btn btn-primary mb-2"><i class="fas fa-bolt"></i> 一键导入 Clash</a>
        <a href="{{ $shadowrocketScheme }}" class="btn btn-primary mb-2"><i class="fas fa-bolt"></i> 导入 Shadowrocket</a>
        <button class="btn btn-outline-primary mb-2" onclick="navigator.clipboard.writeText('{{ $subUrl }}');this.innerHTML='<i class=\'fas fa-check\'></i> 已复制'"><i class="fas fa-copy"></i> 复制订阅链接</button>
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
