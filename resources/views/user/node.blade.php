@extends('layouts.user')
@section('title', '节点设置')
@section('head')
<meta name="turbo-cache-control" content="no-cache">
<style>
.ns-notice { border: none; border-radius: 13px; background: #eef1ff; padding: 16px 20px; margin-bottom: 22px; }
.ns-notice h5 { font-size: 14px; font-weight: 700; color: #4b56d6; margin: 0 0 8px; }
.ns-notice ol { margin: 0; padding-left: 18px; color: #54667a; font-size: 13px; line-height: 1.9; }
.ns-notice a { color: #6777ef; font-weight: 600; }

.ns-card { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; margin-bottom: 22px; }
.ns-card .card-header { border-bottom: 1px solid #f1f3fb; padding: 16px 22px; display: flex; align-items: center; gap: 10px; }
.ns-card .card-header .ic { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; }
.ns-card .card-header h4 { font-size: 15px; font-weight: 700; color: #34395e; margin: 0; }
.ns-card .card-body { padding: 22px; }
.ns-desc { color: #7a869a; font-size: 13px; margin-bottom: 16px; }

.ns-field { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.ns-field .val { flex: 1; min-width: 200px; background: #f6f7fb; border: 1px solid #eef0f5; border-radius: 9px; padding: 11px 14px; font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; color: #34395e; word-break: break-all; display: flex; align-items: center; }
.ns-field .val .muted { color: #98a6ad; letter-spacing: 2px; }
.ns-btn { border-radius: 9px; font-weight: 700; }
.ns-btn-primary { background: linear-gradient(135deg,#6777ef,#5a67e8); border: none; color: #fff; }
.ns-btn-primary:hover { filter: brightness(1.05); color: #fff; }
.ns-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.ns-reset { color: #fc544b; font-weight: 600; font-size: 13px; background: none; border: none; cursor: pointer; padding: 8px 4px; }
.ns-reset:hover { text-decoration: underline; }
</style>
@endsection
@section('content')
<div class="ns-notice">
    <h5><i class="fas fa-shield-alt"></i> 安全须知</h5>
    <ol>
        <li>这里管理<strong>连接凭证</strong>。订阅链接请在「<a href="/user/downloads">下载和教程</a>」或首页获取。</li>
        <li>如要让他人无法再使用，请<strong>同时重置订阅链接和连接凭证</strong>，重置后约 <strong>10 分钟内生效</strong>。</li>
        <li>重置后请删除原有订阅、重新导入新订阅；使用定制客户端则无需操作，等待几分钟即可。</li>
    </ol>
</div>

<div class="card ns-card">
    <div class="card-header"><span class="ic" style="background:#6777ef"><i class="fas fa-link"></i></span><h4>订阅链接</h4></div>
    <div class="card-body">
        <p class="ns-desc">出于安全考虑不显示明文地址，请直接复制使用，或前往下载页一键导入客户端。</p>
        <input type="hidden" id="subUrl" value="{{ $subUrl }}">
        <div class="ns-field">
            <div class="val"><span class="muted">•••••••••••••••••••••••• 订阅链接已隐藏</span></div>
        </div>
        <div class="ns-actions">
            <button class="btn ns-btn ns-btn-primary" id="copySubBtn"><i class="fas fa-copy"></i> 复制订阅链接</button>
            <a href="/user/downloads" class="btn ns-btn btn-outline-primary"><i class="fas fa-download"></i> 前往下载 / 一键导入</a>
            <form method="POST" action="/user/node/reset-sub" class="d-inline" onsubmit="return confirm('重置后旧链接立即失效，约10分钟后新链接生效，确认？')">@csrf
                <button class="ns-reset"><i class="fas fa-sync-alt"></i> 重置订阅链接</button>
            </form>
        </div>
    </div>
</div>

<div class="card ns-card">
    <div class="card-header"><span class="ic" style="background:#7c4ddb"><i class="fas fa-fingerprint"></i></span><h4>连接凭证（UUID）</h4></div>
    <div class="card-body">
        <p class="ns-desc">这是连接节点的身份凭证（VMess 用 UUID）。若怀疑订阅被盗用，重置后旧订阅约 10 分钟内失效。</p>
        <div class="ns-field">
            <div class="val" id="uuidVal">{{ $user->uuid }}</div>
            <button class="btn ns-btn btn-outline-primary" id="copyUuidBtn"><i class="fas fa-copy"></i> 复制</button>
        </div>
        <div class="ns-actions">
            <form method="POST" action="/user/node/reset-passwd" onsubmit="return confirm('重置后需在所有客户端重新导入订阅，约10分钟生效，确认？')">@csrf
                <button class="btn ns-btn ns-btn-primary"><i class="fas fa-sync-alt"></i> 重置连接凭证</button>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    function copy(text, btn, label) {
        var done = function () {
            var old = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> 已复制';
            setTimeout(function () { btn.innerHTML = old; }, 1600);
        };
        if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done, function () { fallback(text); done(); }); }
        else { fallback(text); done(); }
    }
    function fallback(text) {
        var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(t);
    }
    var s = document.getElementById('copySubBtn');
    if (s) s.addEventListener('click', function () { copy(document.getElementById('subUrl').value, s); });
    var u = document.getElementById('copyUuidBtn');
    if (u) u.addEventListener('click', function () { copy(document.getElementById('uuidVal').textContent.trim(), u); });
})();
</script>
@endsection
