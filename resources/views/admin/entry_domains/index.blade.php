@extends('layouts.admin')
@section('title', '入口域名')
@section('content')
<div class="card">
  <div class="card-header"><h4>入口域名池</h4></div>
  <div class="card-body">
    <p class="text-muted mb-3">
      给中转挂一个<strong>稳定域名</strong>：订阅发这个域名、不发中转裸 IP。IP 被墙时，
      你只改这域名的 <strong>A 记录</strong>指到新 IP，客户端<strong>无感</strong>跟过去（不用重发订阅）。
      <br>面板<strong>只登记 + 提醒</strong>——真正改 DNS 仍由你在域名服务商那边做。
      一台中转<strong>只有一个「在用」</strong>域名会被订阅发出；其余是备用，被墙了切过去。
      <br><strong>CNAME 标签（选填，推荐）</strong>：门牌 CNAME 到一个标签、A 记录挂在标签上。
      多个门牌共用一个标签时，<strong>换 IP 只改标签那一条</strong>，全部门牌一起跟；
      按运营商分流的付费 DNS 也只需买在标签那个域名上。
    </p>

    <form method="POST" action="/admin/entry-domains" class="form-row align-items-end mb-4">
      @csrf
      <div class="form-group col-md-3 mb-2">
        <label>入口域名</label>
        <input name="domain" class="form-control" placeholder="cp.example.com" value="{{ old('domain') }}" required>
      </div>
      <div class="form-group col-md-3 mb-2">
        <label>前置中转</label>
        <select name="node_id" class="form-control" required>
          <option value="">选择中转…</option>
          @foreach($relays as $r)
            <option value="{{ $r->id }}" @selected(old('node_id') == $r->id)>{{ $r->name }}（{{ $r->server }}）</option>
          @endforeach
        </select>
      </div>
      <div class="form-group col-md-3 mb-2">
        <label>CNAME 标签<small class="text-muted">（选填）</small></label>
        <input name="cname_target" class="form-control" placeholder="hk1.example.net" value="{{ old('cname_target') }}">
      </div>
      <div class="form-group col-md-3 mb-2">
        <label>当前指向 IP<small class="text-muted">（选填）</small></label>
        <input name="pointed_ip" class="form-control" placeholder="A 记录指的 IP" value="{{ old('pointed_ip') }}">
      </div>
      <div class="form-group col-md-4 mb-2">
        <label>备注<small class="text-muted">（选填）</small></label>
        <input name="note" class="form-control" placeholder="如 DNS 在 Cloudflare" value="{{ old('note') }}">
      </div>
      <div class="form-group col-md-2 mb-2"><label>&nbsp;</label>
        <button class="btn btn-primary btn-block">新增（备用）</button></div>
    </form>
    @error('domain')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
    @error('pointed_ip')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
    @error('cname_target')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr>
          <th>入口域名</th><th>前置中转</th><th>状态</th><th>指向 / DNS</th><th>上次轮换</th><th>操作</th>
        </tr></thead>
        <tbody>
        @forelse($domains as $d)
          <tr>
            <td style="word-break:break-all"><i class="fas fa-globe text-muted"></i> {{ $d->domain }}
              @if($d->isLayered())
                <br><small class="text-muted">CNAME → <span class="mono">{{ $d->cname_target }}</span></small>
              @endif
            </td>
            <td>{{ $d->node->name ?? '—' }}<br><small class="text-muted">{{ $d->node->server ?? '' }}</small></td>
            <td>
              @if($d->status === 'active')<span class="adm-pill ok">在用</span>
              @elseif($d->status === 'blocked')<span class="adm-pill" style="background:#f8d7da;color:#842029">被墙</span>
              @else<span class="adm-pill">备用</span>@endif
            </td>
            <td>
              @if($d->pointed_ip)<span class="mono">{{ $d->pointed_ip }}</span>@else<span class="text-muted">未登记</span>@endif
              @if($d->dnsStale())
                <br><span class="adm-pill" style="background:#fff3cd;color:#664d03" title="A 记录指的 IP 和中转当前真实 IP 对不上">
                  ⚠ 改 {{ $d->dnsRecordHost() }} 的 A 记录 → {{ $d->node->server }}</span>
              @endif
            </td>
            <td class="text-muted">{{ $d->last_rotated_at?->diffForHumans() ?? '—' }}</td>
            <td style="min-width:280px">
              @if($d->status !== 'active')
                <form method="POST" action="/admin/entry-domains/{{ $d->id }}/activate" class="d-inline">@csrf
                  <button class="btn btn-outline-success btn-sm">设为在用</button></form>
              @endif
              @if($d->status !== 'blocked')
                <form method="POST" action="/admin/entry-domains/{{ $d->id }}/block" class="d-inline">@csrf
                  <button class="btn btn-outline-warning btn-sm">标被墙</button></form>
              @endif
              <form method="POST" action="/admin/entry-domains/{{ $d->id }}/rotate" class="d-inline">@csrf
                <div class="input-group input-group-sm d-inline-flex" style="width:180px;vertical-align:middle">
                  <input name="pointed_ip" class="form-control" placeholder="轮换到新 IP" required>
                  <div class="input-group-append"><button class="btn btn-outline-primary">轮换</button></div>
                </div>
              </form>
              <form method="POST" action="/admin/entry-domains/{{ $d->id }}" class="d-inline"
                    data-dgr="删除后订阅将回退用中转裸 IP。">@csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">删除</button></form>
            </td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="adm-empty">还没有入口域名。给中转挂一个稳定域名，客户端就连域名而非裸 IP。</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
