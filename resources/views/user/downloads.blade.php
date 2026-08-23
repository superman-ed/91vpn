@extends('layouts.user')
@section('title', '下载和教程')
@section('head')<meta name="turbo-cache-control" content="no-cache">@endsection
@section('content')
<div class="card">
    <div class="card-header"><h4>91VPN 官方客户端</h4><div class="card-header-action"><span class="badge badge-warning">即将推出</span></div></div>
    <div class="card-body">
        <p class="text-muted">官方定制客户端，登录账号即用，无需手动导入订阅。正在开发中，敬请期待。</p>
        <div class="row">
            @foreach($official as $d)
            <div class="col-6 col-md-3 mb-3">
                <div class="text-center p-3" style="border:1px solid #eee;border-radius:8px">
                    <i class="{{ $d['icon'] }}" style="font-size:36px;color:#6777ef"></i>
                    <div class="mt-2 font-weight-bold">91VPN For {{ $d['os'] }}</div>
                    @if($d['url'])
                        <a href="{{ $d['url'] }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm mt-2">下载</a>
                    @else
                        <button class="btn btn-light btn-sm mt-2" disabled>即将推出</button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h4>通用客户端（现在可用）</h4></div>
    <div class="card-body">
        <p class="text-muted">官方客户端上线前，可先用以下第三方客户端，安装后用下方的一键导入或扫码即可使用。</p>
        <div class="row">
            @foreach($thirdParty as $d)
            <div class="col-6 col-md-3 mb-3">
                <div class="text-center p-3" style="border:1px solid #eee;border-radius:8px">
                    <i class="{{ $d['icon'] }}" style="font-size:36px;color:#6777ef"></i>
                    <div class="mt-2 font-weight-bold">{{ $d['os'] }}</div>
                    <div class="text-muted mb-2" style="font-size:13px">{{ $d['name'] }}</div>
                    <a href="{{ $d['url'] }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">下载</a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

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
                        @if($c['qr_target'] === 'scheme')
                            <a href="{{ $c['scheme'] }}" data-turbo="false" rel="nofollow" class="btn btn-primary btn-sm btn-block"><i class="fas fa-bolt"></i> 一键导入</a>
                        @else
                            <button class="btn btn-outline-primary btn-sm btn-block" onclick="copySub('{{ $c['url'] }}')">复制订阅链接</button>
                        @endif
                    </div>
                    <div class="text-muted mt-1"><small>{{ $c['tip'] }}</small></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h4>订阅链接（各格式）</h4></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table mb-0">
            <tbody>
            @foreach($formatLinks as $fl)
            <tr>
                <td style="font-weight:600">{{ $fl['name'] }}</td>
                <td class="text-right"><button class="btn btn-outline-primary btn-sm" onclick="copySub('{{ $fl['url'] }}')"><i class="fas fa-copy"></i> 复制订阅链接</button></td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</div>

<div class="card">
    <div class="card-header"><h4>使用步骤</h4></div>
    <div class="card-body">
        <ol style="line-height:2">
            <li>下载并安装对应平台客户端</li>
            <li>点上方「一键导入」或复制订阅链接手动添加；手机可扫二维码</li>
            <li>客户端里更新订阅、选择节点、开启代理</li>
            <li>重置订阅/凭证后约 10 分钟生效，需重新导入</li>
        </ol>
    </div>
</div>
@endsection
