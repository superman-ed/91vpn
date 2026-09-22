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

// `[!!]` SNI 与 dest 必须是同一个站。对不上时【未必连不上】——REALITY 对已认证
// 的客户端会替换证书，连接可能照样建立。坏掉的是【伪装】：探测者拿你公布的那个
// SNI 连过来，转发到 dest 之后得到的是 dest 对未知名字的回应。
// `[D]` 真机实测：dest=hkust.edu.hk:443、SNI=www.apple.com 时探测者拿到
// "CN=TRAEFIK DEFAULT CERT" 自签证书 —— 真的 apple.com 永远不会这样。
// 这个配置就是这么在面板上存下来的（追加而非替换），无人拦。
it('拒绝 SNI 与 dest 对不上', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'bad-sni-mismatch', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'tls' => 1,
        'reality_enabled' => 1, 'reality_dest' => 'hkust.edu.hk:443',
        'reality_server_names' => 'www.apple.com',
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertSessionHasErrors('reality_server_names');
    expect(Node::where('name', 'bad-sni-mismatch')->exists())->toBeFalse();
});

// 多个 SNI 时，【任何一个】对不上都要拦 —— 订阅只发第一个，但白名单里的每一个
// 都是探测者可以拿来试的。混进一个不相干的，伪装就有一条缝。
it('多个 SNI 时任一对不上也拒绝', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'bad-sni-mixed', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'tls' => 1,
        'reality_enabled' => 1, 'reality_dest' => 'hkust.edu.hk:443',
        'reality_server_names' => "hkust.edu.hk\nwww.apple.com",
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertSessionHasErrors('reality_server_names');
});

it('同站的子域与 www 变体都放行', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'ok-sni', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'tls' => 1, 'flow' => 'xtls-rprx-vision',
        'reality_enabled' => 1, 'reality_dest' => 'hkust.edu.hk:443',
        'reality_server_names' => "hkust.edu.hk\nwww.hkust.edu.hk\ncdn.hkust.edu.hk",
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertRedirect('/admin/nodes');

    expect(Node::where('name', 'ok-sni')->first()->reality_server_names)->toHaveCount(3);
});

// `[!]` dest 填 IP 时无从比较，且那是有意为之的高级用法 —— 不能拦。
it('dest 是 IP 时跳过这项校验', function () {
    $this->actingAs($this->admin)->post('/admin/nodes', [
        'name' => 'ok-ip-dest', 'server' => 'x.com', 'port' => 443,
        'type' => 'vless', 'net' => 'tcp', 'tls' => 1,
        'reality_enabled' => 1, 'reality_dest' => '1.2.3.4:443',
        'reality_server_names' => 'anything.example',
        'traffic_rate' => 1, 'node_class' => 0,
    ])->assertRedirect('/admin/nodes');
});

// `[!!]` 「抗封锁节点」预设【不能】把 tls 设成 1。
//
// REALITY 与 TLS 是两种安全层，securityLayer() 的口径是 reality > tls > none ——
// 开着 REALITY 时 tls 那个开关读都不读，订阅里给 Clash 发的 tls:true 也是硬编码的。
// 早先预设设了 tls:"1"，结果点完预设，表单自己的组合校验立刻弹一条
// 「REALITY 与 TLS 同时开……TLS 那项可以关掉」—— 预设在生产它自己会报的警告，
// 而新建的每个抗封锁节点都带着这个不生效的开关。
it('抗封锁节点预设不开 TLS', function () {
    $html = $this->actingAs($this->admin)->get('/admin/nodes/create')->assertOk()->getContent();

    expect($html)->toContain('"reality_enabled":"1"');
    expect($html)->toContain('"tls":"0","flow":"xtls-rprx-vision","reality_enabled":"1"');
    // vision 不受影响：它要的是"TLS 或 REALITY"，REALITY 已满足
    expect($html)->toContain('"flow":"xtls-rprx-vision"');
});
