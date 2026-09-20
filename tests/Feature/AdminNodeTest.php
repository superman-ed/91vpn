<?php

use App\Models\Node;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('lists nodes', function () {
    Node::create(['name' => '香港01', 'server' => 'hk.x.com', 'port' => 100, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);
    $this->actingAs($this->admin)->get('/admin/nodes')->assertOk()->assertSee('香港01');
});

it('creates a node with auto-generated secret', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => '日本01', 'server' => 'jp.x.com', 'port' => 10086,
        'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1.5, 'node_class' => 2,
    ])->assertRedirect('/admin/nodes');

    $node = Node::where('name', '日本01')->first();
    expect($node)->not->toBeNull();
    expect($node->node_class)->toBe(2);
    expect(strlen($node->secret))->toBeGreaterThanOrEqual(16);
});

it('updates a node', function () {
    $node = Node::create(['name' => '旧名', 'server' => 'x.com', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);
    $this->actingAs($this->admin)->put("/admin/nodes/{$node->id}", [
        'name' => '新名', 'server' => 'y.com', 'port' => 2, 'type' => 'vmess', 'net' => 'ws', 'traffic_rate' => 2, 'node_class' => 1,
    ])->assertRedirect('/admin/nodes');
    expect($node->fresh()->name)->toBe('新名');
    expect($node->fresh()->net)->toBe('ws');
});

it('deletes a node', function () {
    $node = Node::create(['name' => 'del', 'server' => 'x.com', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 's1']);
    $this->actingAs($this->admin)->delete("/admin/nodes/{$node->id}")->assertRedirect('/admin/nodes');
    expect(Node::find($node->id))->toBeNull();
});

it('blocks normal user from node admin', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->post('/admin/nodes', ['name' => 'x'])->assertForbidden();
});

// `[!!]` vision 组合服务端硬拦(和前端 check() 同口径)——前端只提醒,绕过表单
// 直接 POST 仍能存下坏组合,而 vision 选错是"装完才连不上"那种难查的失败。
it('拒绝 vless+vision 但传输不是 tcp（ws）', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'bad-ws', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'ws', 'flow' => 'xtls-rprx-vision',
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertSessionHasErrors('flow');
    expect(Node::where('name', 'bad-ws')->exists())->toBeFalse();
});

it('拒绝 vless+vision 但既没 TLS 也没 REALITY', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'bad-bare', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'flow' => 'xtls-rprx-vision',
        'tls' => 0, 'traffic_rate' => 1, 'node_class' => 0,
    ])->assertSessionHasErrors('flow');
    expect(Node::where('name', 'bad-bare')->exists())->toBeFalse();
});

it('允许 vless+vision+tcp+tls 的合法组合', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'ok-vision', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'flow' => 'xtls-rprx-vision',
        'tls' => 1, 'traffic_rate' => 1, 'node_class' => 0,
    ])->assertRedirect('/admin/nodes');
    expect(Node::where('name', 'ok-vision')->first()?->flow)->toBe('xtls-rprx-vision');
});

// `[!!]` REALITY 是【安全属性】,填不全时静默降级比报错危险得多:
// 管理员以为开了抗封锁,实际存成明文 vless,页面还提示"保存成功"。
// 两个洞都实际发生过 —— #101 就是带着空 server_names 存下来的。
it('拒绝启用 REALITY 却不填 dest', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'bad-nodest', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'tls' => 1,
        'reality_enabled' => 1, 'reality_dest' => '',
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertSessionHasErrors('reality_dest');
    expect(Node::where('name', 'bad-nodest')->exists())->toBeFalse();
});

it('拒绝启用 REALITY 却不填 server_names', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'bad-nosni', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'tls' => 1,
        'reality_enabled' => 1, 'reality_dest' => 'www.apple.com:443',
        'reality_server_names' => '',
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertSessionHasErrors('reality_server_names');
    expect(Node::where('name', 'bad-nosni')->exists())->toBeFalse();
});

it('允许完整的 REALITY 配置', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'ok-reality', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'tls' => 1, 'flow' => 'xtls-rprx-vision',
        'reality_enabled' => 1, 'reality_dest' => 'www.apple.com:443',
        'reality_server_names' => 'www.apple.com',
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertRedirect('/admin/nodes');

    $n = Node::where('name', 'ok-reality')->first();
    expect($n->reality_server_names)->toBe(['www.apple.com']);
    expect($n->reality_private_key)->not->toBeEmpty();   // 密钥面板自动铸造
});

// `[!]` 守住"不该拦的别拦":把 vless+REALITY 改成 vmess 时,表单只是用 CSS
// 把 REALITY 下拉藏起来、值照样提交。那时管理员的意图【就是】关掉它 ——
// 这种情况必须静默清空,拦下来会让人改不成 vmess。
it('改成 vmess 时静默清空 REALITY,不报错', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'to-vmess', 'server' => 'x.com', 'port' => 443,
        'type' => 'vmess', 'net' => 'tcp',
        'reality_enabled' => 1, 'reality_dest' => '',   // 藏起来的陈旧值
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertRedirect('/admin/nodes');

    $n = Node::where('name', 'to-vmess')->first();
    expect($n)->not->toBeNull();
    expect($n->reality_private_key)->toBeNull();
    expect($n->reality_dest)->toBeNull();
});

// #2 渐进显示:表单按类型/协议隐藏不相关字段(实际显隐是前端 JS,这里守
// 标记与脚本没在改动里掉队 —— 标记没了,字段就永远藏着或永远露着)。
it('节点表单带渐进显示的标记和脚本', function () {
    $html = $this->actingAs($this->admin)->get('/admin/nodes/create')->assertOk()->getContent();
    // vless 专属(Flow / REALITY 开关)与 reality 专属(dest / 扫描候选)两组标记都在
    expect($html)->toContain('data-when="vless"')->toContain('data-when="reality"');
    // 显隐函数与"加载即先算一次"都在,否则打开页面时初始状态是错的
    expect($html)->toContain('function toggle()');
});
