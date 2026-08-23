@extends('layouts.user')
@section('title', '新建工单')
@section('content')
<div class="card">
    <div class="card-header"><h4>新建工单</h4></div>
    <div class="card-body">
        <form method="POST" action="/user/ticket">@csrf
            <div class="form-group"><label>标题</label><input name="subject" class="form-control" required></div>
            <div class="form-group"><label>问题描述</label><textarea name="content" rows="6" class="form-control" required></textarea></div>
            <button class="btn btn-primary">提交</button> <a href="/user/ticket" class="btn btn-light">取消</a>
        </form>
    </div>
</div>
@endsection
