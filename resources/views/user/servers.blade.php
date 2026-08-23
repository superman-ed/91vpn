@extends('layouts.user')
@section('title', '节点列表')
@section('content')
<div class="card"><div class="card-header"><h4>可用节点</h4></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>名称</th><th>类型</th><th>倍率</th><th>状态</th></tr></thead><tbody>
@forelse($nodes as $n)
<tr><td>{{ $n->name }}</td><td>{{ strtoupper($n->type) }}</td>
<td>@if($n->traffic_rate==1)<span class="badge badge-success">{{ $n->traffic_rate }}x</span>@else<span class="badge badge-warning">{{ $n->traffic_rate }}x</span>@endif</td>
<td>@if($n->online)<span class="badge badge-success">在线</span>@else<span class="badge badge-danger">离线</span>@endif</td></tr>
@empty<tr><td colspan="4" class="text-muted">暂无可用节点（购买套餐后解锁更多）</td></tr>@endforelse
</tbody></table></div></div>
<div class="card-footer text-muted"><small>倍率越低越省流量。节点导入见「节点设置」。</small></div></div>
@endsection
