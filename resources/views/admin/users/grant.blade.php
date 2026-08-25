@extends('layouts.admin')
@section('title', '开通套餐')
@section('content')
<div class="adm-head">
    <h4><i class="fas fa-gift text-primary"></i> 为 {{ $user->email }} 开通套餐</h4>
    <a href="/admin/users" class="btn btn-light" style="border-radius:9px">返回</a>
</div>

<form method="POST" action="/admin/users/{{ $user->id }}/grant" class="adm-form">@csrf
    <div class="card adm-form-card">
        <div class="card-header"><span class="ic"><i class="fas fa-box"></i></span><h4>选择套餐</h4></div>
        <div class="card-body">
            <p class="form-tip">直接给该用户发放套餐权益（不收费，记为管理员订单）。若当前套餐未过期，到期日在原基础上顺延。</p>
            @if($plans->isEmpty())
                <div class="adm-empty"><i class="fas fa-box-open fa-2x mb-2 d-block"></i>暂无可开通套餐，请先在「套餐管理」创建</div>
            @else
            <div class="form-group" style="max-width:480px">
                <label>套餐</label>
                <select name="plan_id" class="form-control @error('plan_id') is-invalid @enderror" required>
                    @foreach($plans as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} · {{ period_name($p->period) }} · {{ $p->transfer_gb }}GB · {{ $p->duration_days }}天 · {{ class_name($p->class) }}</option>
                    @endforeach
                </select>
                @error('plan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button class="btn adm-btn"><i class="fas fa-check"></i> 确认开通</button>
            @endif
        </div>
    </div>
</form>
@endsection
