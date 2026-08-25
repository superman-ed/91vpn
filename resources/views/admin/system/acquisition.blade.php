@extends('layouts.admin')
@section('title', '来路统计')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-route text-primary"></i> 来路 / 获客统计 <span class="text-muted" style="font-size:13px;font-weight:400">按注册来源</span></h4>
    <form method="GET" class="adm-search adm-tools">
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:auto"><span class="text-muted">~</span>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:auto">
        <button class="btn adm-btn"><i class="fas fa-search"></i> 筛选</button>
        @if($from || $to)<a href="/admin/system/acquisition" class="btn btn-light" style="border-radius:9px">清除</a>@endif
    </form>
</div>

<div class="ad-stats" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#6777ef,#4d5ed0)"><div style="font-size:22px;font-weight:800">{{ number_format($total) }}</div><div style="font-size:12.5px;opacity:.9">区间注册用户</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#63c76a,#3fae57)"><div style="font-size:22px;font-weight:800">{{ number_format($channels->get('邀请注册', 0)) }}</div><div style="font-size:12.5px;opacity:.9">邀请拉新</div></div>
    <div style="flex:1;min-width:150px;border-radius:13px;padding:16px 20px;color:#fff;background:linear-gradient(135deg,#3aa0c7,#2a86ab)"><div style="font-size:22px;font-weight:800">{{ number_format($channels->get('直接访问', 0)) }}</div><div style="font-size:12.5px;opacity:.9">直接访问</div></div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card adm-panel" style="margin-bottom:18px">
            <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-sitemap text-primary"></i> 渠道分布</h4></div>
            <div style="padding:12px 20px 18px">
                @php $maxC = max(1, $channels->max() ?? 1); @endphp
                @forelse($channels as $name => $cnt)
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                    <div style="width:88px;flex:0 0 88px;font-size:13px;color:#34395e;font-weight:600;text-align:right">{{ $name }}</div>
                    <div style="flex:1;background:#f1f3fb;border-radius:6px;height:22px;overflow:hidden"><div style="height:100%;width:{{ round($cnt / $maxC * 100) }}%;background:linear-gradient(90deg,#63c76a,#3fae57);border-radius:6px;min-width:2px"></div></div>
                    <div style="width:78px;flex:0 0 78px;font-size:12.5px;color:#54667a">{{ $cnt }} · {{ $total > 0 ? round($cnt / $total * 100) : 0 }}%</div>
                </div>
                @empty<div class="adm-empty" style="padding:24px 0">暂无注册数据</div>@endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card adm-panel" style="margin-bottom:18px">
            <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-globe text-primary"></i> 外部来路 TOP <span class="text-muted" style="font-weight:400;font-size:12px">（HTTP referer 域名）</span></h4></div>
            <div style="padding:12px 20px 18px">
                @forelse($referers as $host => $cnt)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f4f6fb">
                    <span style="font-size:13px;color:#34395e;font-family:SFMono-Regular,Menlo,Consolas,monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:75%">{{ $host }}</span>
                    <span class="adm-pill info">{{ $cnt }}</span>
                </div>
                @empty<div class="adm-empty" style="padding:24px 0">暂无外部来路<br><small>用户从外站链接跳转注册时，这里记录其来源域名</small></div>@endforelse
            </div>
        </div>
    </div>
</div>

<div class="card adm-panel">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-trophy text-warning"></i> 拉新榜 TOP5 <span class="text-muted" style="font-weight:400;font-size:12px">（邀请注册最多的用户）</span></h4></div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;padding:12px 20px 18px">
        @forelse($topInviters as $i => $t)
        <div style="display:flex;align-items:center;gap:10px;background:#fafbff;border:1px solid #eef1f8;border-radius:11px;padding:9px 15px;min-width:220px">
            <span style="width:22px;height:22px;border-radius:50%;background:{{ ['#f5a623','#9aa5b1','#c98a5e'][$i] ?? '#c3cbd6' }};color:#fff;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center">{{ $i + 1 }}</span>
            <div style="flex:1;min-width:0"><div style="color:#34395e;font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $t['user']?->email ?? '已删除用户' }}</div></div>
            <div style="color:#2fa84f;font-weight:800;font-size:14px">{{ $t['cnt'] }} 人</div>
        </div>
        @empty<div class="adm-empty" style="padding:24px 0;width:100%">暂无邀请注册</div>@endforelse
    </div>
</div>
@endsection
