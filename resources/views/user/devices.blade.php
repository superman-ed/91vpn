@extends('layouts.user')
@section('title', '在线设备')
@section('head')
<style>
.dv-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.dv-bar h4 { font-size: 17px; font-weight: 700; color: #34395e; margin: 0; }
.dv-bar .meta { font-size: 13px; color: #7a869a; }
.dv-bar .meta b { color: #34395e; }
.dv-bar .over { color: #fc544b; }

.dv-panel { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; }
.dv-table { margin: 0; }
.dv-table thead th { border: none; background: #fafbff; color: #98a6ad; font-size: 12px; font-weight: 600; padding: 12px 22px; }
.dv-table tbody td { border-top: 1px solid #f4f6fb; padding: 14px 22px; font-size: 13.5px; color: #54667a; vertical-align: middle; }
.dv-table tbody tr:hover { background: #fafbff; }
.dv-ip { font-family: SFMono-Regular, Menlo, Consolas, monospace; color: #34395e; font-weight: 600; }
.dv-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #47c363; margin-right: 7px; box-shadow: 0 0 0 3px rgba(71,195,99,.16); }
.dv-empty { text-align: center; color: #98a6ad; padding: 44px 0; }
.dv-note { display: flex; gap: 13px; align-items: flex-start; background: linear-gradient(135deg,#fff7ec,#fff);
    border: 1px solid #ffe6c7; border-radius: 13px; padding: 16px 18px; margin-top: 16px; }
.dv-note .ic { width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg,#ffb020,#ff9f1a); color: #fff; font-size: 16px; }
.dv-note h6 { font-size: 14px; font-weight: 700; color: #34395e; margin: 0 0 4px; }
.dv-note p { font-size: 13px; color: #7a869a; margin: 0; line-height: 1.65; }
.dv-note a { color: #6777ef; font-weight: 600; }
</style>
@endsection
@section('content')
<div class="dv-bar">
    <h4><i class="fas fa-laptop text-primary"></i> 在线设备</h4>
    <span class="meta">
        当前在线 <b>{{ $onlineCount }}</b> 台
        @if($limit > 0)· 设备上限 <b class="{{ $onlineCount > $limit ? 'over' : '' }}">{{ $limit }}</b> 台@else· 不限设备数@endif
    </span>
</div>

<div class="card dv-panel">
    <div class="table-responsive">
        <table class="table dv-table">
            <thead><tr><th>IP 地址</th><th>归属地</th><th>接入节点</th><th>最近活跃</th></tr></thead>
            <tbody>
            @forelse($devices as $d)
            <tr>
                <td><span class="dv-dot"></span><span class="dv-ip">{{ $d['ip'] }}</span></td>
                <td>{{ $d['location'] }}</td>
                <td>{{ $d['node'] }}</td>
                <td class="text-muted">{{ $d['last_seen']?->diffForHumans() }}</td>
            </tr>
            @empty
            <tr><td colspan="4"><div class="dv-empty"><i class="fas fa-laptop-house fa-2x mb-2 d-block"></i>暂无在线设备<br><span style="font-size:12.5px">连接节点开始使用后，这里会显示正在使用你账号的设备。</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="dv-note">
    <span class="ic"><i class="fas fa-shield-alt"></i></span>
    <div>
        <h6>发现不认识的设备？</h6>
        <p>这里列出最近正在使用你账号的 IP。若出现陌生归属地，可能是账号或订阅被他人使用。到 <a href="/user/node">「节点设置」</a> <b>重置 UUID</b> 即可让所有旧设备立即失效，只有用新凭证的设备才能继续连接。设备数据由节点每分钟上报，断开后约 2 分钟自动消失。</p>
    </div>
</div>
@endsection
