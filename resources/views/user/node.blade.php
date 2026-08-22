@extends('layouts.user')
@section('title', '节点设置')
@section('content')
<div class="panel">
    <h3>订阅链接</h3>
    <p style="font-size:13px;color:#6c757d">把下面的链接导入 Clash / v2rayN 等客户端即可使用。</p>
    <p><code id="sub">{{ $subUrl }}</code></p>
    <button class="btn ghost" onclick="navigator.clipboard.writeText('{{ $subUrl }}');this.textContent='已复制'">复制订阅链接</button>
    <form method="POST" action="/user/node/reset-sub" style="display:inline" onsubmit="return confirm('重置后旧链接立即失效，确认？')">
        @csrf<button class="btn danger">重置订阅链接</button>
    </form>
</div>

<div class="panel">
    <h3>连接密码</h3>
    <p style="font-size:13px;color:#6c757d">当前连接密码：<code>{{ $user->passwd }}</code>。⚠️ 重置会同时变更 UUID，需重新导入订阅。</p>
    <form method="POST" action="/user/node/reset-passwd" onsubmit="return confirm('重置会同时变更 UUID，确认？')">
        @csrf<button class="btn">随机重置连接密码</button>
    </form>
</div>
@endsection
