@extends('layouts.admin')
@section('title', '工单管理')
@section('content')
@php $tabs = ['' => '全部', 'open' => '进行中', 'closed' => '已关闭']; @endphp
<div class="adm-head">
    <h4><i class="fas fa-headset text-primary"></i> 工单管理</h4>
    <div class="adm-tools">
        @foreach($tabs as $k => $label)
        <a href="/admin/tickets{{ $k ? '?status='.$k : '' }}" class="btn btn-sm {{ (string) $status === $k ? 'adm-btn' : 'btn-light' }}" style="border-radius:9px">{{ $label }} <span style="opacity:.7">{{ $k ? ($counts[$k] ?? 0) : $counts['all'] }}</span></a>
        @endforeach
    </div>
</div>

<div class="card adm-panel">
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>ID</th><th>用户</th><th>标题</th><th>状态</th><th>最后更新</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($tickets as $t)
            <tr>
                <td class="text-muted">#{{ $t->id }}</td>
                <td style="color:#34395e;font-weight:600">{{ $t->user?->email ?? '—' }}</td>
                <td>{{ $t->subject }}</td>
                <td>@if($t->status === 'open')<span class="adm-pill info">进行中</span>@else<span class="adm-pill muted">已关闭</span>@endif</td>
                <td class="text-muted">{{ $t->updated_at?->format('Y-m-d H:i') }}</td>
                <td><a href="/admin/tickets/{{ $t->id }}" class="btn btn-outline-primary btn-sm">处理</a></td>
            </tr>
            @empty<tr><td colspan="6"><div class="adm-empty"><i class="fas fa-headset fa-2x mb-2 d-block"></i>暂无工单</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($tickets->hasPages())<div class="adm-foot">{{ $tickets->links('pagination::bootstrap-4') }}</div>@endif
</div>
@endsection
