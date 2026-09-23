@extends('layouts.user')
@section("title", "我的设备")
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
    <h4><i class="fas fa-laptop text-primary"></i> 我的设备</h4>
    <span class="meta">
        已登记 <b class="{{ $limit > 0 && $devices->count() > $limit ? 'over' : '' }}">{{ $devices->count() }}</b> 台
        @if($limit > 0)· 套餐上限 <b>{{ $limit }}</b> 台@else· 不限设备数@endif
    </span>
</div>

{{-- `[!!]` 上下两块【不是同一件事】，刻意分开显示：
     上：设备（客户端上报的 device_id）—— 套餐上限管的就是这个
     下：最近连接的 IP（节点上报）—— 只用来发现陌生归属地
     此前这一页只有下面那块，标题却写「在线设备」、旁边挂着「设备上限」，
     而那个上限自 e379f3a 起管的是上面这块。一台手机 Wi-Fi 切蜂窝
     会在下面留两行、在上面始终是一台 —— 混在一起用户必然看错。 --}}
<div class="card dv-panel mb-3">
    @if($devices->isEmpty())
    <div class="dv-empty"><i class="fas fa-mobile-alt fa-2x mb-2 d-block"></i>还没有登记的设备<br>
        <span style="font-size:12.5px">用 91VPN 官方客户端（安卓 / Windows）登录后会自动登记。
        用小火箭、Clash 等第三方客户端连接【不会】出现在这里，但同样可以正常使用。</span></div>
    @else
    <div class="table-responsive">
        <table class="table dv-table">
            <thead><tr><th>设备</th><th>平台</th><th>客户端版本</th><th>最近归属地</th><th>最近活跃</th></tr></thead>
            <tbody>
            @foreach($devices as $d)
            <tr>
                <td><span class="dv-dot"></span><b>{{ $d['name'] }}</b></td>
                <td>{{ $d['platform'] }}</td>
                <td class="text-muted">{{ $d['app_version'] }}</td>
                <td>{{ $d['location'] }}</td>
                <td class="text-muted">{{ $d['last_seen']?->diffForHumans() }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="dv-bar">
    <h4><i class="fas fa-globe text-primary"></i> 最近连接的 IP</h4>
    <span class="meta">{{ $ips->count() }} 个 · <span class="text-muted">不计入设备上限</span></span>
</div>

<div class="card dv-panel">
    @if($ips->isEmpty())
    <div class="dv-empty"><i class="fas fa-plug fa-2x mb-2 d-block"></i>暂无连接记录<br>
        <span style="font-size:12.5px">连接节点开始使用后，这里会显示最近的接入 IP。</span></div>
    @else
    <div class="table-responsive">
        <table class="table dv-table">
            <thead><tr><th>IP 地址</th><th>归属地</th><th>接入节点</th><th>最近活跃</th></tr></thead>
            <tbody>
            @foreach($ips as $d)
            <tr>
                <td><span class="dv-dot"></span><span class="dv-ip">{{ $d['ip'] }}</span></td>
                <td>{{ $d['location'] }}</td>
                <td>{{ $d['node'] }}</td>
                <td class="text-muted">{{ $d['last_seen']?->diffForHumans() }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="dv-note">
    <span class="ic"><i class="fas fa-shield-alt"></i></span>
    <div>
        <h6>发现不认识的设备或归属地？</h6>
        <p>到 <a href="/user/node">「节点设置」</a> <b>重置 UUID</b>，所有旧凭证立即失效，只有重新导入订阅的设备才能继续连接。
        IP 记录由节点每分钟上报，断开后约 2 分钟自动消失；设备记录则会保留，超过 15 天没有连接的会在额度满时被自动回收。</p>
    </div>
</div>
@endsection
