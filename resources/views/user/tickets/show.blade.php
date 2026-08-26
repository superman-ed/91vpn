@extends('layouts.user')
@section('title', '工单详情')
@section('head')
<style>
.tkd-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.tkd-head .back { color: #7a869a; font-size: 14px; text-decoration: none; }
.tkd-head .back:hover { color: #6777ef; }
.tkd-head h4 { font-size: 18px; font-weight: 700; color: #34395e; margin: 0; flex: 1; min-width: 0; }
.tkd-status { font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 20px; }
.tkd-status.open { background: #e7f3ff; color: #3a8ee6; }
.tkd-status.closed { background: #f2f3f5; color: #98a6ad; }

.chat { background: #fff; border: none; border-radius: 14px; box-shadow: 0 5px 18px rgba(103,119,239,.08); padding: 22px; }
.msg { display: flex; gap: 10px; margin-bottom: 18px; align-items: flex-end; }
.msg.me { flex-direction: row-reverse; }
.msg-av { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; font-weight: 700; flex-shrink: 0; }
.msg-av.staff { background: linear-gradient(135deg,#63c76a,#3fae57); }
.msg-av.user { background: linear-gradient(135deg,#6777ef,#5a67e8); }
.msg-wrap { max-width: 74%; }
.msg-meta { font-size: 11.5px; color: #98a6ad; margin: 0 4px 4px; }
.msg.me .msg-meta { text-align: right; }
.msg-bubble { padding: 11px 15px; border-radius: 14px; font-size: 14px; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
.msg.them .msg-bubble { background: #f4f6fb; color: #34395e; border-bottom-left-radius: 4px; }
.msg.me .msg-bubble { background: linear-gradient(135deg,#6777ef,#5a67e8); color: #fff; border-bottom-right-radius: 4px; }

.reply-box { border-top: 1px solid #f1f3fb; margin-top: 6px; padding-top: 18px; }
.reply-box textarea { border-radius: 11px; border-color: #eef0f5; }
.reply-btn { border-radius: 9px; font-weight: 700; background: linear-gradient(135deg,#6777ef,#5a67e8); border: none; color: #fff; }
.reply-btn:hover { filter: brightness(1.05); color: #fff; }
.tk-closed-note { text-align: center; color: #98a6ad; padding: 16px; border-top: 1px solid #f1f3fb; margin-top: 6px; }
</style>
@endsection
@section('content')
<div class="tkd-head">
    <a href="/user/ticket" class="back"><i class="fas fa-arrow-left"></i> 返回</a>
    <h4>{{ $ticket->subject }}</h4>
    <span class="tkd-status {{ $ticket->status === 'open' ? 'open' : 'closed' }}">{{ $ticket->status === 'open' ? '进行中' : '已关闭' }}</span>
</div>

<div class="chat">
    @foreach($ticket->replies as $r)
    <div class="msg {{ $r->is_admin ? 'them' : 'me' }}">
        <span class="msg-av {{ $r->is_admin ? 'staff' : 'user' }}">{{ $r->is_admin ? '客' : mb_strtoupper(mb_substr($r->user?->name ?: 'U', 0, 1)) }}</span>
        <div class="msg-wrap">
            <div class="msg-meta">{{ $r->is_admin ? '客服' : ($r->user?->name ?? '我') }} · {{ $r->created_at?->format('Y-m-d H:i') }}</div>
            <div class="msg-bubble">{{ $r->content }}</div>
        </div>
    </div>
    @endforeach

    @if($ticket->status === 'open')
    <div class="reply-box">
        <form method="POST" action="/user/ticket/{{ $ticket->id }}/reply">@csrf
            <div class="form-group mb-2"><textarea name="content" rows="3" class="form-control" placeholder="输入回复内容…" required></textarea></div>
            <div class="d-flex align-items-center" style="gap:10px">
                <button class="btn reply-btn"><i class="fas fa-paper-plane"></i> 发送回复</button>
                <button type="submit" formaction="/user/ticket/{{ $ticket->id }}/close" formnovalidate class="btn btn-light" style="border-radius:9px;margin-left:auto"><i class="fas fa-check"></i> 问题已解决，关闭工单</button>
            </div>
        </form>
    </div>
    @else
    <div class="tk-closed-note"><i class="fas fa-lock"></i> 工单已关闭，如需继续咨询请新建工单。</div>
    @endif
</div>
@endsection
