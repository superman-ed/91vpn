@extends('layouts.admin')
@section('title', '站内信')
@section('content')
@php $typeName = ['system' => '系统', 'expiry' => '到期提醒', 'marketing' => '营销', 'notice' => '通知']; @endphp
<div class="adm-head">
    <h4><i class="fas fa-paper-plane text-primary"></i> 站内信 <span class="text-muted" style="font-size:13px;font-weight:400">推送到用户消息中心</span></h4>
</div>

@if(session('status'))<div class="alert alert-success" style="border-radius:10px">{{ session('status') }}</div>@endif

<div class="card adm-panel" style="margin-bottom:18px">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-pen text-primary"></i> 发送站内信</h4></div>
    <form method="POST" action="/admin/notifications" style="padding:14px 20px 18px">@csrf
        {{-- 发送方式 --}}
        <div style="display:flex;gap:18px;margin-bottom:14px">
            <label style="display:flex;align-items:center;gap:6px;margin:0;cursor:pointer"><input type="radio" name="mode" value="batch" checked onclick="document.getElementById('segWrap').style.display='block';document.getElementById('emailWrap').style.display='none'"> 群发(按人群)</label>
            <label style="display:flex;align-items:center;gap:6px;margin:0;cursor:pointer"><input type="radio" name="mode" value="single" onclick="document.getElementById('segWrap').style.display='none';document.getElementById('emailWrap').style.display='block'"> 单发(指定用户)</label>
        </div>

        <div id="segWrap" class="row">
            <div class="form-group col-md-6"><label style="font-size:13px;color:#7a869a;font-weight:600">发送人群</label>
                <select name="segment" class="form-control" style="border-radius:9px">
                    @foreach($segments as $k => $label)<option value="{{ $k }}">{{ $label }}（{{ $segmentCounts[$k] }} 人）</option>@endforeach
                </select>
            </div>
        </div>
        <div id="emailWrap" class="row" style="display:none">
            <div class="form-group col-md-6"><label style="font-size:13px;color:#7a869a;font-weight:600">用户邮箱</label><input name="email" class="form-control" placeholder="user@example.com" style="border-radius:9px"></div>
        </div>

        <div class="row">
            <div class="form-group col-md-9"><label style="font-size:13px;color:#7a869a;font-weight:600">标题</label><input name="title" value="{{ old('title') }}" class="form-control" placeholder="如：五一活动 / 系统维护通知" style="border-radius:9px" required></div>
            <div class="form-group col-md-3"><label style="font-size:13px;color:#7a869a;font-weight:600">类型</label><select name="type" class="form-control" style="border-radius:9px"><option value="system">系统</option><option value="notice">通知</option><option value="marketing">营销</option></select></div>
            <div class="form-group col-md-12"><label style="font-size:13px;color:#7a869a;font-weight:600">内容</label><textarea name="content" rows="4" class="form-control" placeholder="消息正文，支持换行" style="border-radius:9px" required>{{ old('content') }}</textarea></div>
            <div class="form-group col-md-12">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0"><input type="checkbox" name="pinned" value="1" style="width:16px;height:16px"> <span style="font-size:13px;color:#34395e">登录弹窗提醒</span> <small class="text-muted">（勾选后用户下次进入会自动弹窗显示，必须点"知道了"才关闭；适合重要通知）</small></label>
            </div>
        </div>
        @error('email')<div class="text-danger" style="font-size:12.5px;margin-bottom:8px">{{ $message }}</div>@enderror
        <button class="btn adm-btn" style="border-radius:9px" data-dgr="确认发送这条站内信？群发将立即推送给所选人群的全部用户。"><i class="fas fa-paper-plane"></i> 发送</button>
    </form>
</div>

<div class="card adm-panel">
    <div class="card-header" style="border:none;padding:16px 20px 4px"><h4 style="font-size:14px;color:#34395e;margin:0"><i class="fas fa-history text-primary"></i> 近期发送</h4></div>
    <div class="table-responsive">
        <table class="table adm-table">
            <thead><tr><th>时间</th><th>标题</th><th>类型</th><th>发送数</th><th>已读率</th><th>操作</th></tr></thead>
            <tbody>
            @forelse($recent as $r)
            <tr>
                <td class="text-muted">{{ \Illuminate\Support\Carbon::parse($r->sent_at)->format('Y-m-d H:i') }}</td>
                <td style="color:#34395e;font-weight:600">{{ $r->title }}@if($r->pinned)<span class="adm-pill primary" style="margin-left:6px;font-size:10px">弹窗</span>@endif</td>
                <td><span class="adm-pill {{ $r->type === 'expiry' ? 'warn' : ($r->type === 'marketing' ? 'primary' : 'info') }}">{{ $typeName[$r->type] ?? $r->type }}</span></td>
                <td>{{ $r->cnt }}</td>
                <td>@php $rate = $r->cnt > 0 ? round($r->read_cnt / $r->cnt * 100) : 0; @endphp<span style="color:{{ $rate >= 50 ? '#2fa84f' : '#98a6ad' }}">{{ $rate }}%</span> <span class="text-muted" style="font-size:12px">({{ $r->read_cnt }}/{{ $r->cnt }})</span></td>
                <td style="white-space:nowrap">
                    @if($r->type !== 'expiry')<button type="button" class="btn btn-outline-primary btn-sm" onclick="editNoti(@js($r->batch_id), @js($r->title), @js($r->content))">编辑</button>@endif
                    <form method="POST" action="/admin/notifications/{{ $r->batch_id }}" class="d-inline" data-dgr="撤回后将从这 {{ $r->cnt }} 位用户的消息箱彻底删除该站内信，确认？">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">撤回</button></form>
                </td>
            </tr>
            @empty<tr><td colspan="6"><div class="adm-empty"><i class="fas fa-paper-plane fa-2x mb-2 d-block"></i>还没发过站内信</div></td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- 编辑弹层 --}}
<div id="editNotiModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1090;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:14px;width:520px;max-width:92vw;padding:22px">
        <h5 style="font-weight:700;color:#34395e;margin-bottom:6px">编辑站内信</h5>
        <p class="text-muted" style="font-size:12.5px;margin-bottom:14px">修改后，所有已收到该站内信的用户会同步看到新内容（不重置已读）。</p>
        <form method="POST" id="editNotiForm">@csrf @method('PUT')
            <div class="form-group"><label style="font-size:13px;color:#7a869a;font-weight:600">标题</label><input name="title" id="en-title" class="form-control" style="border-radius:9px" required></div>
            <div class="form-group"><label style="font-size:13px;color:#7a869a;font-weight:600">内容</label><textarea name="content" id="en-content" rows="5" class="form-control" style="border-radius:9px" required></textarea></div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-light" style="border-radius:9px" onclick="document.getElementById('editNotiModal').style.display='none'">取消</button><button class="btn adm-btn" style="border-radius:9px">保存</button></div>
        </form>
    </div>
</div>
<script>
function editNoti(batch, title, content) {
    document.getElementById('editNotiForm').action = '/admin/notifications/' + batch;
    document.getElementById('en-title').value = title;
    document.getElementById('en-content').value = content;
    document.getElementById('editNotiModal').style.display = 'flex';
}
</script>
@endsection
