@extends('layouts.user')
@section('title', '流量明细')
@section('head')
<style>
.tc-stats { display: flex; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
.tc-stat { flex: 1; min-width: 150px; border: none; border-radius: 14px; padding: 18px 20px; color: #fff; box-shadow: 0 6px 20px rgba(103,119,239,.18); }
.tc-stat.a { background: linear-gradient(135deg,#6777ef,#5a67e8); }
.tc-stat.b { background: linear-gradient(135deg,#63c76a,#3fae57); box-shadow: 0 6px 20px rgba(99,199,106,.2); }
.tc-stat.c { background: linear-gradient(135deg,#7c4ddb,#6636c0); box-shadow: 0 6px 20px rgba(124,77,219,.2); }
.tc-stat .n { font-size: 26px; font-weight: 800; line-height: 1.1; }
.tc-stat .t { font-size: 12.5px; opacity: .9; margin-top: 4px; }

.tc-card { border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); overflow: hidden; margin-bottom: 22px; }
.tc-card .card-header { border-bottom: 1px solid #f1f3fb; padding: 16px 22px; }
.tc-card .card-header h4 { font-size: 15px; font-weight: 700; color: #34395e; margin: 0; }
.tc-card .card-body { padding: 20px 22px; }

.tc-chart { display: flex; align-items: flex-end; gap: 4px; height: 170px; }
.tc-bar { flex: 1; min-width: 5px; background: linear-gradient(180deg,#8b98f5,#6777ef); border-radius: 4px 4px 0 0; min-height: 3px; transition: filter .15s; cursor: default; }
.tc-bar:hover { filter: brightness(1.12); }
.tc-bar.zero { background: #eef0f5; }
.tc-axis { display: flex; justify-content: space-between; margin-top: 8px; font-size: 11px; color: #98a6ad; }

.tc-table { margin: 0; }
.tc-table thead th { border: none; background: #fafbff; color: #98a6ad; font-size: 12px; font-weight: 600; padding: 12px 22px; }
.tc-table tbody td { border-top: 1px solid #f4f6fb; padding: 13px 22px; font-size: 13.5px; color: #54667a; vertical-align: middle; }
.tc-table tbody tr:hover { background: #fafbff; }
.tc-up { color: #3a8ee6; } .tc-down { color: #63c76a; } .tc-total { color: #34395e; font-weight: 700; }
.tc-empty { text-align: center; color: #98a6ad; padding: 44px 0; }
</style>
@endsection
@section('content')
@php
    $count = $records->count();
    $avg = $count ? $total / $count : 0;
@endphp
<div class="tc-stats">
    <div class="tc-stat a"><div class="n">{{ number_format(bytes_to_gb($total), 2) }} <small style="font-size:15px">GB</small></div><div class="t">近 30 天总用量</div></div>
    <div class="tc-stat b"><div class="n">{{ number_format(bytes_to_gb($avg), 2) }} <small style="font-size:15px">GB</small></div><div class="t">日均用量</div></div>
    <div class="tc-stat c"><div class="n">{{ $count }} <small style="font-size:15px">天</small></div><div class="t">有记录天数</div></div>
</div>

@if($chart->isNotEmpty())
<div class="card tc-card">
    <div class="card-header"><h4><i class="fas fa-chart-column text-primary"></i> 每日用量趋势</h4></div>
    <div class="card-body">
        <div class="tc-chart">
            @foreach($chart as $r)
            @php $sum = $r->u + $r->d; $h = max(3, round($sum / $maxDay * 100)); @endphp
            <div class="tc-bar {{ $sum == 0 ? 'zero' : '' }}" style="height:{{ $h }}%" title="{{ $r->date->format('m-d') }}：{{ number_format(bytes_to_gb($sum), 2) }} GB"></div>
            @endforeach
        </div>
        <div class="tc-axis">
            <span>{{ $chart->first()->date->format('m-d') }}</span>
            <span>{{ $chart->last()->date->format('m-d') }}</span>
        </div>
    </div>
</div>
@endif

<div class="card tc-card">
    <div class="card-header"><h4><i class="fas fa-list text-primary"></i> 流量明细（近 30 天）</h4></div>
    <div class="table-responsive">
        <table class="table tc-table">
            <thead><tr><th>日期</th><th>上传</th><th>下载</th><th>合计</th></tr></thead>
            <tbody>
            @forelse($records as $r)
            <tr>
                <td style="color:#34395e;font-weight:600">{{ $r->date->format('Y-m-d') }}</td>
                <td class="tc-up"><i class="fas fa-arrow-up" style="font-size:10px"></i> {{ number_format(bytes_to_gb($r->u), 2) }} GB</td>
                <td class="tc-down"><i class="fas fa-arrow-down" style="font-size:10px"></i> {{ number_format(bytes_to_gb($r->d), 2) }} GB</td>
                <td class="tc-total">{{ number_format(bytes_to_gb($r->u + $r->d), 2) }} GB</td>
            </tr>
            @empty<tr><td colspan="4"><div class="tc-empty"><i class="fas fa-chart-area fa-2x mb-2 d-block"></i>暂无流量记录</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
