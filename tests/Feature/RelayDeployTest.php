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
