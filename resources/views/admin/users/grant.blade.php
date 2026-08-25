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
            <style>
                .grant-plan { display:flex; align-items:center; gap:12px; border:1.5px solid #eef0f5; border-radius:11px; padding:12px 16px; margin-bottom:10px; cursor:pointer; transition:all .15s; }
                .grant-plan:hover { border-color:#c9d0f7; }
                .grant-plan.active { border-color:#6777ef; background:#f5f6ff; }
                .grant-plan input { display:none; }
                .grant-plan .nm { font-weight:700; color:#34395e; }
                .grant-plan .meta { font-size:12.5px; color:#7a869a; margin-top:2px; }
                .grant-plan .chk { margin-left:auto; color:#6777ef; opacity:0; }
                .grant-plan.active .chk { opacity:1; }
            </style>
            <div style="max-width:560px">
                @foreach($plans as $p)
                <label class="grant-plan {{ $loop->first ? 'active' : '' }}" onclick="document.querySelectorAll('.grant-plan').forEach(e=>e.classList.remove('active'));this.classList.add('active')">
                    <input type="radio" name="plan_id" value="{{ $p->id }}" {{ $loop->first ? 'checked' : '' }} required>
                    <span>
                        <span class="nm">{{ $p->name }}</span>
                        <span class="meta">{{ period_name($p->period) }} · {{ $p->transfer_gb }}GB · {{ $p->duration_days }}天 · {{ class_name($p->class) }}</span>
                    </span>
                    <i class="fas fa-circle-check chk"></i>
                </label>
                @endforeach
            </div>
            @error('plan_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <button class="btn adm-btn mt-2"><i class="fas fa-check"></i> 确认开通</button>
            @endif
        </div>
    </div>
</form>
@endsection
