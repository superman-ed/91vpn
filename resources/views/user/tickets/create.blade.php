@extends('layouts.user')
@section('title', '新建工单')
@section('content')
<div class="panel"><form method="POST" action="/user/ticket">@csrf
<label>标题</label><input name="subject" style="width:100%" required>
<label>问题描述</label><textarea name="content" rows="6" style="width:100%" required></textarea>
<div style="margin-top:16px"><button class="btn">提交</button> <a href="/user/ticket" class="btn ghost">取消</a></div>
</form></div>
@endsection
