@extends('layouts.user')
@section('title', '订阅记录')
@section('head')
<style>
.sl-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.sl-bar h4 { font-size: 17px; font-weight: 700; color: #34395e; margin: 0; }
.sl-bar .meta { font-size: 13px; color: #7a869a; }

.sl-panel { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; }
.sl-table { margin: 0; }
.sl-table thead th { border: none; background: #fafbff; color: #98a6ad; font-size: 12px; font-weight: 600; padding: 12px 22px; }
.sl-table tbody td { border-top: 1px solid #f4f6fb; padding: 13px 22px; font-size: 13.5px; color: #54667a; vertical-align: middle; }
.sl-table tbody tr:hover { background: #fafbff; }
.sl-client { display: inline-flex; align-items: center; gap: 9px; }
.sl-cic { width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; flex-shrink: 0; }
.sl-cname { font-weight: 600; color: #34395e; }
.sl-ua { font-size: 11px; color: #b0bac5; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sl-ip { font-family: SFMono-Regular, Menlo, Consolas, monospace; color: #34395e; }
.sl-empty { text-align: center; color: #98a6ad; padding: 44px 0; }
.sl-foot { padding: 14px 22px; color: #98a6ad; font-size: 12.5px; border-top: 1px solid #f4f6fb; }
</style>
@endsection
@section('content')
@php
    $parse = function ($ua) {
        $ua = (string) $ua;
        $map = [
            'clash' => ['Clash', '#3fae57'], 'mihomo' => ['Mihomo', '#3fae57'], 'stash' => ['Stash', '#3fae57'],
            'shadowrocket' => ['Shadowrocket', '#3a6ee6'], 'quantumult' => ['Quantumult', '#6777ef'],
            'surge' => ['Surge', '#e6912a'], 'loon' => ['Loon', '#7c4ddb'], 'sing-box' => ['sing-box', '#34395e'],
            'v2ray' => ['V2Ray', '#e64b4b'], 'shadowsocks' => ['Shadowsocks', '#3aa0c7'],
        ];
        foreach ($map as $kw => $c) {
            if (stripos($ua, $kw) !== false) {
                return $c;
            }
        }
        return ['其它客户端', '#98a6ad'];
    };
@endphp
<div class="sl-bar">
    <h4><i class="fas fa-rss text-primary"></i> 订阅使用记录</h4>
    <span class="meta">近 {{ $logs->count() }} 次拉取 · {{ $logs->pluck('ip')->unique()->count() }} 个不同 IP</span>
</div>

<div class="card sl-panel">
    <div class="table-responsive">
        <table class="table sl-table">
            <thead><tr><th>客户端</th><th>IP 地址</th><th>地点</th><th>时间</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
            @php $c = $parse($l->client); @endphp
            <tr>
                <td>
                    <span class="sl-client">
                        <span class="sl-cic" style="background:{{ $c[1] }}"><i class="fas fa-mobile-screen-button"></i></span>
                        <span>
                            <span class="sl-cname">{{ $c[0] }}</span>
                            @if($l->client)<br><span class="sl-ua" title="{{ $l->client }}">{{ $l->client }}</span>@endif
                        </span>
                    </span>
                </td>
                <td><span class="sl-ip">{{ $l->ip }}</span></td>
                <td>{{ $l->location ?: '—' }}</td>
                <td class="text-muted">{{ $l->fetched_at?->format('Y-m-d H:i:s') }}</td>
            </tr>
            @empty<tr><td colspan="4"><div class="sl-empty"><i class="fas fa-rss fa-2x mb-2 d-block"></i>暂无订阅记录</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="sl-foot"><i class="fas fa-shield-alt"></i> 如发现陌生 IP 拉取订阅，请到「节点设置」重置订阅链接。</div>
</div>
@endsection
