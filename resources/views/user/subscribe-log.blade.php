@extends('layouts.user')
@section('title', '订阅记录')
@section('content')
<div class="card"><div class="card-header"><h4>订阅使用记录（近30条）</h4></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>IP</th><th>地点</th><th>客户端</th><th>时间</th></tr></thead><tbody>
@forelse($logs as $l)
<tr><td>{{ $l->ip }}</td><td>{{ $l->location ?: '—' }}</td><td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $l->client }}">{{ $l->client ?: '—' }}</td><td>{{ $l->fetched_at?->format('Y-m-d H:i:s') }}</td></tr>
@empty<tr><td colspan="4" class="text-muted">暂无订阅记录</td></tr>@endforelse
</tbody></table></div></div>
<div class="card-footer text-muted"><small>如发现陌生 IP 拉取订阅，请重置订阅链接。</small></div></div>
@endsection
