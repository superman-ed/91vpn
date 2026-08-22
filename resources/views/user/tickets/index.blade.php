@extends('layouts.user')
@section('title', '工单支持')
@section('content')
<div class="panel"><h3>我的工单 <a href="/user/ticket/create" class="btn" style="padding:6px 12px">新建工单</a></h3>
<table>
<tr><th>标题</th><th>状态</th><th>最后更新</th><th></th></tr>
@forelse($tickets as $t)
<tr><td>{{ $t->subject }}</td><td>{{ $t->status === 'open' ? '进行中' : '已关闭' }}</td>
<td>{{ $t->updated_at?->format('Y-m-d H:i') }}</td><td><a href="/user/ticket/{{ $t->id }}">查看</a></td></tr>
@empty<tr><td colspan="4" style="color:#acb5c9">暂无工单</td></tr>@endforelse
</table></div>
@endsection
