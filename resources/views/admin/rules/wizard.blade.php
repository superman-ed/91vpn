@extends('layouts.admin')
@section('title', '中转链路向导')
@section('content')

<div class="section-header"><h1>中转链路向导</h1></div>

<div class="card">
  <div class="card-header"><span class="ic"><i class="fas fa-magic"></i></span><h4>一次配好一条中转链路</h4></div>
  <div class="card-body">

    <p class="text-muted">
      选一台中转、一台落地、一个端口，向导会建好转发规则，
      <strong>并自动打开落地的「接受 PROXY 头」</strong>。
    </p>

    {{-- `[!!]` 说清它替你做了什么、以及为什么值得替你做。
         这一步手工配时在另一个页面，漏了的后果是两端都不报错、只有客户端连不上。 --}}
    <div class="alert alert-light border">
      <strong style="color:#34395e">它替你做的那一步</strong>
      <p class="mb-0 mt-1" style="font-size:13px;line-height:1.7">
        中转发 PROXY 头、落地就必须收头，这两件事必须成对。
        手工配的时候要跑到落地节点页去开另一个开关 ——
        而漏掉的表现是：<strong>中转日志正常、落地一行都没有，只有客户端连不上</strong>。
        这是整套中转配置里最容易漏、也最难查的一步。
      </p>
    </div>

    @if ($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul></div>
    @endif

    @if ($relays->isEmpty() || $landings->isEmpty())
      <div class="alert alert-warning">
        @if ($relays->isEmpty())还没有<strong>中转</strong>角色的节点。@endif
        @if ($landings->isEmpty())还没有<strong>落地</strong>角色的节点。@endif
        先去<a href="/admin/nodes/create">添加节点</a>（新建页有「中转节点」预设）。
      </div>
    @else
    <form method="POST" action="/admin/rules/wizard">@csrf
      <div class="row">
        <div class="form-group col-md-4">
          <label>中转节点（用户连的那一台）</label>
          <select name="relay_id" class="form-control" required>
            @foreach ($relays as $r)
              <option value="{{ $r->id }}" @selected(old('relay_id') == $r->id)>
                {{ $r->name }}（{{ $r->server }}）{{ $r->online ? '' : ' · 离线' }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="form-group col-md-4">
          <label>落地节点（流量最终出去的那一台）</label>
          <select name="landing_id" class="form-control" required>
            @foreach ($landings as $l)
              <option value="{{ $l->id }}" @selected(old('landing_id') == $l->id)>
                {{ $l->name }}（{{ $l->server }}:{{ $l->port }}）
              </option>
            @endforeach
          </select>
        </div>
        <div class="form-group col-md-4">
          <label>中转上的监听端口</label>
          <input name="listen_port" type="number" min="1" max="65535" value="{{ old('listen_port', 30001) }}" class="form-control" required>
          <small class="text-muted">用户将要连这个端口 —— 记得在中转机的防火墙放行</small>
        </div>
      </div>

      <div class="form-group">
        <div class="custom-control custom-checkbox">
          <input type="checkbox" name="send_proxy" value="1" class="custom-control-input" id="sp" {{ old('send_proxy', true) ? 'checked' : '' }}>
          <label class="custom-control-label" for="sp">
            让落地看到<strong>用户的真实 IP</strong>（PROXY protocol）
          </label>
        </div>
        <small class="text-muted d-block mt-1">
          不开的话，落地看到的所有连接都来自中转那一个 IP，在线 IP 统计和审计就失去意义。<br>
          <span class="text-danger">开了之后落地那个端口必须只对中转可达</span> ——
          PROXY 头没有认证，谁能连上谁就能伪造来源 IP。向导会在创建后提醒你。
        </small>
      </div>

      <button class="btn btn-primary"><i class="fas fa-check mr-1"></i>创建这条链路</button>
      <a href="/admin/rules" class="btn btn-outline-secondary">取消</a>
    </form>
    @endif
  </div>
</div>
@endsection
