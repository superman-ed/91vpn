@extends('layouts.user')
@section('title', '流量明细')
@section('content')
<div class="card"><div class="card-header"><h4>流量明细（近30天）</h4></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>日期</th><th>上传</th><th>下载</th><th>合计</th></tr></thead><tbody>
@forelse($records as $r)
<tr><td>{{ $r->date->format('Y-m-d') }}</td>
<td>{{ number_format(bytes_to_gb($r->u),2) }} GB</td>
<td>{{ number_format(bytes_to_gb($r->d),2) }} GB</td>
<td><strong>{{ number_format(bytes_to_gb($r->u+$r->d),2) }} GB</strong></td></tr>
@empty<tr><td colspan="4" class="text-muted">暂无流量记录</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
