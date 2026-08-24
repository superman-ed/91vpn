@extends('layouts.user')
@section('title', '节点列表')
@section('head')
<style>
.nodes-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.nodes-bar h4 { font-size: 17px; font-weight: 700; color: #34395e; margin: 0; }
.nodes-count { font-size: 13px; color: #7a869a; }
.nodes-count .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #63c76a; margin-right: 4px; vertical-align: middle; }

.node-card {
    border: none; border-radius: 13px; background: #fff; padding: 16px 18px;
    box-shadow: 0 4px 16px rgba(103,119,239,.08); transition: transform .18s, box-shadow .18s; height: 100%;
    display: flex; flex-direction: column;
}
.node-card:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(103,119,239,.16); }
.node-card.offline { opacity: .6; }
.node-top { display: flex; align-items: center; gap: 12px; }
.node-flag { width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,.12); }
.node-flag img { width: 100%; height: 100%; object-fit: cover; display: block; }
.node-flag.globe { background: #6777ef; color: #fff; font-size: 16px; }
.node-name { font-size: 15px; font-weight: 700; color: #34395e; line-height: 1.2; word-break: break-all; }
.node-status { margin-left: auto; font-size: 12px; font-weight: 600; white-space: nowrap; }
.node-status .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
.node-status.on { color: #2fa84f; } .node-status.on .dot { background: #63c76a; box-shadow: 0 0 0 3px rgba(99,199,106,.2); }
.node-status.off { color: #98a6ad; } .node-status.off .dot { background: #cfd6df; }
.node-tags { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 14px; }
.node-tag { font-size: 11.5px; font-weight: 600; padding: 3px 9px; border-radius: 6px; background: #f1f3fb; color: #6777ef; }
.node-tag.rate-ok { background: #e9f9ed; color: #2fa84f; }
.node-tag.rate-hi { background: #fff5e6; color: #e6912a; }
.node-tag.vip { background: #f3ecff; color: #7c4ddb; }
.nodes-empty { text-align: center; color: #98a6ad; padding: 50px 0; }
</style>
@endsection
@section('content')
@php
    // 地区关键词 → ISO 国家码(对应 public/flags/*.svg 真实国旗)
    $regions = [
        '香港' => 'hk', 'HK' => 'hk',
        '台湾' => 'tw', '臺灣' => 'tw', 'TW' => 'tw',
        '日本' => 'jp', 'JP' => 'jp', '东京' => 'jp', '大阪' => 'jp',
        '新加坡' => 'sg', '狮城' => 'sg', 'SG' => 'sg',
        '美国' => 'us', 'US' => 'us', '洛杉矶' => 'us', '硅谷' => 'us',
        '韩国' => 'kr', 'KR' => 'kr', '首尔' => 'kr',
        '英国' => 'gb', 'UK' => 'gb',
        '德国' => 'de', 'DE' => 'de', '法国' => 'fr', 'FR' => 'fr',
        '俄罗斯' => 'ru', 'RU' => 'ru', '加拿大' => 'ca',
        '澳大利亚' => 'au', '澳洲' => 'au',
        '印度' => 'in', '泰国' => 'th', '马来' => 'my', '越南' => 'vn',
        '土耳其' => 'tr', '荷兰' => 'nl', '巴西' => 'br', '阿根廷' => 'ar', '菲律宾' => 'ph',
    ];
    $iso = function ($name) use ($regions) {
        foreach ($regions as $kw => $code) {
            if (mb_stripos($name, $kw) !== false) {
                return $code;
            }
        }
        return null;   // 未识别 → 地球图标
    };
    $rate = fn ($r) => rtrim(rtrim(number_format($r, 2), '0'), '.');
@endphp
<div class="nodes-bar">
    <h4><i class="fas fa-server text-primary"></i> 可用节点</h4>
    <span class="nodes-count"><span class="dot"></span>{{ $nodes->count() }} 个在线节点 · 倍率越低越省流量</span>
</div>

@if($nodes->isEmpty())
    <div class="card" style="border:none;border-radius:14px"><div class="nodes-empty"><i class="fas fa-server fa-2x mb-3 d-block"></i>暂无可用节点<br><small>购买套餐后可解锁更多节点</small></div></div>
@else
<div class="row">
    @foreach($nodes as $n)
    <div class="col-6 col-md-4 col-lg-3 mb-4">
        <div class="node-card {{ $n->online ? '' : 'offline' }}">
            <div class="node-top">
                @php $code = $iso($n->name); @endphp
                @if($code)
                <span class="node-flag"><img src="/flags/{{ $code }}.svg" alt="" loading="lazy"></span>
                @else
                <span class="node-flag globe"><i class="fas fa-globe"></i></span>
                @endif
                <span class="node-name">{{ $n->name }}</span>
                <span class="node-status {{ $n->online ? 'on' : 'off' }}"><span class="dot"></span>{{ $n->online ? '在线' : '离线' }}</span>
            </div>
            <div class="node-tags">
                <span class="node-tag">{{ strtoupper($n->type) }}</span>
                <span class="node-tag">{{ strtoupper($n->net) }}</span>
                <span class="node-tag {{ $n->traffic_rate <= 1 ? 'rate-ok' : 'rate-hi' }}">{{ $rate($n->traffic_rate) }}x 倍率</span>
                @if($n->node_class > 0)<span class="node-tag vip">{{ class_name($n->node_class) }}+</span>@endif
                @if($n->speed_limit > 0)<span class="node-tag">限速 {{ $n->speed_limit }}M</span>@endif
            </div>
        </div>
    </div>
    @endforeach
</div>
<div class="text-muted"><small><i class="fas fa-info-circle"></i> 节点导入 / 订阅配置请见「节点设置」页。</small></div>
@endif
@endsection
