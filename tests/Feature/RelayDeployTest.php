<?php

use App\Models\DeployRun;
use App\Models\Node;
use App\Models\User;

/**
 * ADR-008 P4b：一键部署搬入本面板。
 *
 * `[!!]` 这组不真去 SSH 装机 —— 那需要一台真机，且属于运维动作。
 * 守的是三件在搬家时会坏、而且坏了很危险的事：
 *   · 节点列表页能渲染（部署 UI 是模态框 + JS，最容易引用到原面板才有的东西）；
 *   · 端点有鉴权（部署=拿着凭据在远端执行脚本，谁都能打就完了）；
 *   · **凭据不落库** —— DeployRun 里只记连到哪，不记密码/私钥。
 */
function deployAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function deployNode(): Node
{
    return Node::create([
        'name' => 'DMIT 中转', 'server' => '179.253.249.78', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'S', 'role' => 'relay',
    ]);
}

it('节点列表页带上部署入口后仍能渲染', function () {
    deployNode();
    $this->actingAs(deployAdmin())->get('/admin/nodes')->assertOk()->assertSee('js-deploy', false);
});

it('部署端点要管理员', function () {
    $n = deployNode();
    $this->post("/admin/nodes/{$n->id}/deploy")->assertRedirect('/login');
});

it('普通用户打不动部署端点', function () {
    $n = deployNode();
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->post("/admin/nodes/{$n->id}/deploy")->assertForbidden();
});

// `[!!]` 凭据只在那一次请求里存在：后端用管道喂给脱离出去的后台进程。
// 这条守的是"不要哪天顺手把它存进 deploy_runs 方便排查"。
it('部署记录里不存任何凭据', function () {
    $cols = \Illuminate\Support\Facades\Schema::getColumnListing('deploy_runs');
    foreach (['password', 'passwd', 'private_key', 'key', 'secret', 'credential'] as $bad) {
        expect(in_array($bad, $cols, true))->toBeFalse("deploy_runs 有 {$bad} 列 —— 凭据不该落库");
    }
});

it('部署日志端点只给本节点的记录', function () {
    $a = deployNode();
    $b = Node::create([
        'name' => '别的节点', 'server' => '5.5.5.5', 'port' => 0, 'type' => 'vmess',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'S2', 'role' => 'relay',
    ]);
    $run = DeployRun::create([
        'node_id' => $b->id, 'status' => 'ok', 'ssh_host' => '5.5.5.5',
        'ssh_port' => 22, 'ssh_user' => 'root', 'log' => 'x',
    ]);

    // 拿 A 节点的 URL 去读 B 节点的部署记录 —— 不该给
    $this->actingAs(deployAdmin())
        ->getJson("/admin/nodes/{$a->id}/deploy/{$run->id}")
        ->assertStatus(404);
});

// `[!!]` phpseclib 的 exec 回调:返回 true = 【中止】(close_channel 后立刻返回)。
// Deployer 原来 return true,于是每次部署都在远端第一行输出后被掐断,
// 日志停在 "==> 目标机架构 x86_64",退出码拿不到 —— 一键部署从来没成过一次。
// 这条守的就是那个返回值。
it('输出回调把每一行喂出去,并且不中止读取', function () {
    $lines = [];
    $ret = app(\App\Services\Deployer::class)
        ->emitLines("==> 一\r\n==> 二\n\n==> 三\n", function ($l) use (&$lines) {
            $lines[] = $l;
        });

    expect($lines)->toBe(['==> 一', '==> 二', '==> 三']);
    expect($ret)->toBeFalse();   // true 会让 phpseclib 掐断通道
});

// `[!!]` 归 ufw 管的机器上,往 INPUT 链尾 -A 一条 DROP 是【无效】的:
// ufw 的跳转排在前面,包在那里就被 ACCEPT 了。实测目标机 ufw 放行了
// 39000:40000/tcp,追加的 DROP 形同虚设 —— 而面板日志照样打印
// "其余 DROP",看起来配好了,其实端口对全世界敞着。
// 对 accept_proxy 节点这最危险:PROXY 头无认证,能连上就能伪造客户端 IP。
it('防火墙片段在 ufw 机器上走 ufw,且 allow 排在 deny 之前', function () {
    $m = new ReflectionMethod(\App\Services\Deployer::class, 'firewallSnippet');
    $m->setAccessible(true);
    $fw = $m->invoke(app(\App\Services\Deployer::class), [
        'accept_proxy' => true, 'proxy_port' => 39500,
        'allow_src' => ['1.2.3.4', '127.0.0.1'],
    ], '');

    expect($fw)->toContain('ufw status')                       // 先判断有没有 ufw
        ->toContain('ufw --force insert 1 deny')               // 用 insert,不是追加
        ->toContain('ufw --force insert 1 allow');
    // deny 必须先插,allow 后插才会排在它前面 —— 顺序反了等于全放行。
    expect(strpos($fw, 'insert 1 deny'))->toBeLessThan(strpos($fw, 'insert 1 allow'));
    // 裸 iptables 分支同理:先 DROP 后 ACCEPT(-I 是往前插)。
    expect(strpos($fw, '--dport 39500 -j DROP'))->toBeLessThan(strpos($fw, '--dport 39500 -s "$ip" -j ACCEPT'));
});

// 开了 accept_proxy 却没给端口/源:必须显眼告警,不能静默什么都不做。
it('accept_proxy 缺端口或源 IP 时告警', function () {
    $m = new ReflectionMethod(\App\Services\Deployer::class, 'firewallSnippet');
    $m->setAccessible(true);
    $fw = $m->invoke(app(\App\Services\Deployer::class),
        ['accept_proxy' => true, 'proxy_port' => 0, 'allow_src' => []], '');

    expect($fw)->toContain('未配防火墙');
});
