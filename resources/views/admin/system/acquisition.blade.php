@extends('layouts.admin')
@section('title', '来路统计')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-route text-primary"></i> 来路 / 获客统计 <span class="text-muted" style="font-size:13px;font-weight:400">渠道来源 · UTM · 转化质量</span></h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($from || $to)<a href="/admin/system/acquisition" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($total) }}</div><div style="font-size:12.5px;opacity:.9">区间注册用户</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ number_format($paidTotal) }}</div><div style="font-size:12.5px;opacity:.9">其中付费用户</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#3aa0c7,#2a86ab)"><div style="font-size:22px;font-weight:800">{{ $total > 0 ? round($paidTotal / $total * 100, 1) : 0 }}%</div><div style="font-size:12.5px;opacity:.9">整体付费率</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#ffb020,#ff9f1a)"><div style="font-size:22px;font-weight:800">¥{{ number_format($revenueTotal, 0) }}</div><div style="font-size:12.5px;opacity:.9">带来营收</div></div>
</div>

{{-- 渠道转化质量 --}}
<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-filter text-primary"></i> 渠道转化质量 <span class="text-muted" style="font-weight:400;font-size:12px">（哪个渠道的用户真花钱，按营收排序）</span></h4></div>
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>渠道</th><th>注册数</th><th>付费用户</th><th>付费率</th><th>带来营收</th></tr></thead>
            <tbody>
            @forelse($channelRows as $c)
            <tr>
                <td style="color:#34395e;font-weight:600">{{ $c['channel'] }}</td>
                <td>{{ $c['reg'] }}</td>
                <td>{{ $c['paid'] }}</td>
                <td>
                    @php $rate = $c['rate']; $col = $rate >= 20 ? '#2fa84f' : ($rate >= 8 ? '#e6912a' : '#98a6ad'); @endphp
                    <span style="color:{{ $col }};font-weight:700">{{ $rate }}%</span>
                </td>
                <td style="font-weight:700;color:#e6960f">¥{{ number_format($c['revenue'], 2) }}</td>
            </tr>
            @empty<tr><td colspan="5"><div class="adm-empty">暂无渠道数据</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card adm-panel" style="margin-bottom:18px">
            <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-bullhorn text-primary"></i> 推广来源 <span class="text-muted" style="font-weight:400;font-size:12px">（utm_source）</span></h4></div>
            <div style="padding:12px 20px 18px">
                @php $maxS = max(1, $utmSource->max() ?? 1); @endphp
                @forelse($utmSource as $src => $cnt)
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                    <div style="width:110px;flex:0 0 110px;font-size:13px;color:#34395e;font-weight:600;text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $src }}</div>
                    <div style="flex:1;background:#f1f3fb;border-radius:6px;height:20px;overflow:hidden"><div style="height:100%;width:{{ round($cnt / $maxS * 100) }}%;background:linear-gradient(90deg,#6777ef,#8b98ff);border-radius:6px;min-width:2px"></div></div>
                    <div style="width:46px;flex:0 0 46px;font-size:12.5px;color:#54667a">{{ $cnt }}</div>
                </div>
                @empty<div class="adm-empty" style="padding:20px 0">暂无 UTM 来源<br><small>推广链接带 <code style="font-size:11px">?utm_source=xxx&utm_campaign=yyy</code> 即可追踪</small></div>@endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card adm-panel" style="margin-bottom:18px">
            <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-flag text-primary"></i> 推广活动 <span class="text-muted" style="font-weight:400;font-size:12px">（utm_campaign）</span></h4></div>
            <div style="padding:12px 20px 18px">
                @forelse($utmCampaign as $cmp => $cnt)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f4f6fb">
                    <span style="font-size:13px;color:#34395e">{{ $cmp }}</span><span class="adm-pill info">{{ $cnt }}</span>
                </div>
                @empty<div class="adm-empty" style="padding:20px 0">暂无活动数据</div>@endforelse
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card adm-panel" style="margin-bottom:18px">
            <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-globe text-primary"></i> 外部来路 <span class="text-muted" style="font-weight:400;font-size:12px">（HTTP referer，无 UTM 时）</span></h4></div>
            <div style="padding:12px 20px 18px">
                @forelse($referers as $host => $cnt)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f4f6fb">
                    <span style="font-size:13px;color:#34395e;font-family:SFMono-Regular,Menlo,Consolas,monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:75%">{{ $host }}</span><span class="adm-pill info">{{ $cnt }}</span>
                </div>
                @empty<div class="adm-empty" style="padding:20px 0">暂无外部来路</div>@endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card adm-panel" style="margin-bottom:18px">
            <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-trophy text-warning"></i> 拉新榜 TOP5 <span class="text-muted" style="font-weight:400;font-size:12px">（邀请注册最多）</span></h4></div>
            <div style="padding:12px 20px 18px">
                @forelse($topInviters as $i => $t)
                <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #f4f6fb">
                    <span style="width:20px;height:20px;border-radius:50%;background:{{ ['#f5a623','#9aa5b1','#c98a5e'][$i] ?? '#c3cbd6' }};color:#fff;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center">{{ $i + 1 }}</span>
                    <span style="flex:1;font-size:13px;color:#34395e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $t['user']?->email ?? '已删除用户' }}</span>
                    <span style="color:#2fa84f;font-weight:700;font-size:13px">{{ $t['cnt'] }} 人</span>
                </div>
                @empty<div class="adm-empty" style="padding:20px 0">暂无邀请注册</div>@endforelse
            </div>
        </div>
    </div>
</div>
@endsection
