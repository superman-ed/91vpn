@extends('layouts.admin')
@section('title', '工单处理')
@section('content')
<style>
.atk-chat { padding: 6px 4px 0; }
.atk-msg { display: flex; gap: 10px; margin-bottom: 18px; align-items: flex-end; }
.atk-msg.me { flex-direction: row-reverse; }
.atk-av { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.atk-av.staff { background: linear-gradient(135deg,#6777ef,#5a67e8); }
.atk-av.user { background: linear-gradient(135deg,#63c76a,#3fae57); }
.atk-wrap { max-width: 74%; }
.atk-meta { font-size: 11.5px; color: #98a6ad; margin: 0 4px 4px; }
.atk-msg.me .atk-meta { text-align: right; }
.atk-bubble { padding: 11px 15px; border-radius: 14px; font-size: 14px; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
.atk-msg.them .atk-bubble { background: #f4f6fb; color: #34395e; border-bottom-left-radius: 4px; }
.atk-msg.me .atk-bubble { background: linear-gradient(135deg,#6777ef,#5a67e8); color: #fff; border-bottom-right-radius: 4px; }
.atk-reply textarea { border-radius: 11px; border-color: #eef0f5; }
</style>
<div class="adm-head">
    <h4><i class="fas fa-headset text-primary"></i> {{ $ticket->subject }}</h4>
    <div class="adm-tools">
        <span class="text-muted" style="font-size:13px">来自 {{ $ticket->user?->email }}</span>
        @if($ticket->status === 'open')<span class="adm-pill info">进行中</span>@else<span class="adm-pill muted">已关闭</span>@endif
        <a href="/admin/tickets" class="btn btn-light btn-sm" style="border-radius:9px">返回</a>
    </div>
</div>

<div class="card adm-panel"><div class="card-body" style="padding:22px">
    <div class="atk-chat">
        @foreach($ticket->replies as $r)
        <div class="atk-msg {{ $r->is_admin ? 'me' : 'them' }}">
            <span class="atk-av {{ $r->is_admin ? 'staff' : 'user' }}">{{ $r->is_admin ? '客' : mb_strtoupper(mb_substr($r->user?->email ?: 'U', 0, 1)) }}</span>
            <div class="atk-wrap">
                <div class="atk-meta">{{ $r->is_admin ? '客服' : ($r->user?->email ?? '用户') }} · {{ $r->created_at?->format('Y-m-d H:i') }}</div>
                <div class="atk-bubble">{{ $r->content }}</div>
            </div>
        </div>
        @endforeach

        @if($ticket->status === 'open')
        <div style="border-top:1px solid #f1f3fb;margin-top:6px;padding-top:18px">
            <form method="POST" action="/admin/tickets/{{ $ticket->id }}/reply" class="atk-reply">@csrf
                <div class="form-group mb-2"><textarea name="content" rows="3" class="form-control" placeholder="回复用户…" required></textarea></div>
                <button class="btn adm-btn"><i class="fas fa-paper-plane"></i> 回复</button>
            </form>
            <form method="POST" action="/admin/tickets/{{ $ticket->id }}/close" class="d-inline" data-dgr="确认关闭该工单？">@csrf
                <button class="btn btn-outline-danger" style="border-radius:9px"><i class="fas fa-lock"></i> 关闭工单</button>
            </form>
        </div>
        @else
        <div class="text-center" style="border-top:1px solid #f1f3fb;margin-top:6px;padding-top:16px">
            <span class="text-muted"><i class="fas fa-lock"></i> 工单已关闭</span>
            <form method="POST" action="/admin/tickets/{{ $ticket->id }}/reopen" class="d-inline" style="margin-left:10px">@csrf
                <button class="btn btn-light btn-sm" style="border-radius:9px"><i class="fas fa-lock-open"></i> 重开工单</button>
            </form>
        </div>
        @endif
    </div>
</div></div>
@endsection
