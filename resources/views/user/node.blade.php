@extends('layouts.user')
@section('title', '节点设置')
@section('head')<meta name="turbo-cache-control" content="no-cache">@endsection
@section('content')
<div class="alert alert-info">
    <ol class="mb-0" style="padding-left:18px;line-height:1.9">
        <li>这里管理连接凭证。<strong>订阅链接请在「<a href="/user/downloads">下载和教程</a>」或首页获取。</strong></li>
        <li>如要让他人无法再使用，请<strong>同时重置订阅链接和连接凭证</strong>，重置后约 <strong>10 分钟内生效</strong>。</li>
        <li>重置后请删除原有订阅、重新导入新订阅；如使用定制客户端则无需操作，等待几分钟即可生效。</li>
    </ol>
</div>

<div class="card">
    <div class="card-header"><h4>订阅链接</h4></div>
    <div class="card-body">
        <p class="text-muted">出于安全考虑不显示明文地址，请直接复制使用。</p>
        <button class="btn btn-outline-primary" onclick="copySub('{{ $subUrl }}')"><i class="fas fa-copy"></i> 复制订阅链接</button>
        <a href="/user/downloads" class="btn btn-primary"><i class="fas fa-download"></i> 前往下载 / 一键导入</a>
        <form method="POST" action="/user/node/reset-sub" class="d-inline" onsubmit="return confirm('重置后旧链接立即失效，约10分钟后新链接生效，确认？')">@csrf<button class="btn btn-danger">重置订阅链接</button></form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h4>连接凭证</h4></div>
    <div class="card-body">
        <p class="text-muted">
            当前 UUID：<code>{{ $user->uuid }}</code><br>
            这是连接节点的身份凭证（VMess 用 UUID）。若怀疑订阅被盗用，重置后旧订阅约 10 分钟内失效。
        </p>
        <form method="POST" action="/user/node/reset-passwd" onsubmit="return confirm('重置后需在所有客户端重新导入订阅，约10分钟生效，确认？')">@csrf<button class="btn btn-primary">重置连接凭证（UUID）</button></form>
    </div>
</div>
@endsection
