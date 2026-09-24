<?php

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ─────────────────────────────────────────────────────────────────
// `[!!]` 2026-09-24 实测:管理员重置密码后,【什么都没被吊销】——
//     重置前  设备 token 200 · 账号 token 200
//     重置后  设备 token 200 · 账号 token 200 · 订阅 200
//   因为 ClientToken 认的是 DeviceToken.token 或 users.api_token,
//   两者与密码【完全无关】且是长效的。而管理员按这个按钮的场景几乎只有
//   两个:用户丢了密码,或者账号被盗 —— 后者正是要切断已经拿到凭据的人。
// ─────────────────────────────────────────────────────────────────

function pwVictim(): User
{
    return User::factory()->create([
        'username' => 'pwvictim', 'password' => 'oldpassword123',
        'invite_token' => 'PWVICTIMSUB', 'class' => 1, 'class_expire' => now()->addMonth(),
        'transfer_enable' => 10 * 1024 ** 3, 'u' => 0, 'd' => 0,
    ]);
}

function pwAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'password' => 'adminpass123']);
}

it('重置密码会吊销设备 token 与账号 token', function () {
    $u = pwVictim();
    $stolen = DeviceToken::issue($u, 'attacker-device');
    $accountToken = $u->fresh()->api_token;

    // 事前两者都能用 —— 不先证明这一点,后面的 401 可能只是 token 本来就没生效
    $this->withHeader('Authorization', "Bearer {$stolen}")->getJson('/api/user')->assertOk();
    $this->withHeader('Authorization', "Bearer {$accountToken}")->getJson('/api/user')->assertOk();

    $this->actingAs(pwAdmin())
        ->post("/admin/users/{$u->id}/reset-password", ['password' => 'brandnewpass456'])
        ->assertRedirect();

    expect(Hash::check('brandnewpass456', $u->fresh()->password))->toBeTrue();

    $this->withHeader('Authorization', "Bearer {$stolen}")->getJson('/api/user')->assertStatus(401);
    $this->withHeader('Authorization', "Bearer {$accountToken}")->getJson('/api/user')->assertStatus(401);
    expect(DeviceToken::where('user_id', $u->id)->count())->toBe(0);
});

// `[!]` 订阅 token 刻意不动:换掉它会让用户必须重新导入订阅,
//   那是独立的、用户自己有入口的动作(/user/node/reset-sub)。
it('订阅链接刻意不受影响 —— 那是另一个动作', function () {
    $u = pwVictim();
    $this->actingAs(pwAdmin())
        ->post("/admin/users/{$u->id}/reset-password", ['password' => 'brandnewpass456']);

    expect($u->fresh()->invite_token)->toBe('PWVICTIMSUB');
    $this->get('/sub/PWVICTIMSUB')->assertOk();
});

// `[!!]` 修完仍可能是【虚假的安全感】:代理连接靠 uuid,不靠这些 token。
//   所以界面必须告诉管理员它切不断什么,否则他会以为账号被盗已经处理完了。
it('提示里说清它切不断代理连接，并指向正确的工具', function () {
    $u = pwVictim();
    $res = $this->actingAs(pwAdmin())
        ->post("/admin/users/{$u->id}/reset-password", ['password' => 'brandnewpass456']);

    $msg = (string) session('status');
    expect($msg)->toContain('吊销')
        ->toContain('不影响代理连接')
        ->toContain('封禁');
});

// `[!]` 对照:账号被盗的正确动作是封禁 —— 它是真的切得断的那个。
//   `[!!]` 顺带补一个覆盖缺口:全仓【没有任何测试】覆盖"封禁 → 从节点用户名单
//   消失"这件事(只有 NodeDrainTest / TrafficBatchTest 用到 servableUsers)。
//   而这正是账号被盗时唯一真正起作用的动作。
it('对照：封禁会把用户从节点用户名单里去掉', function () {
    $u = User::factory()->create([
        'username' => 'bantest', 'password' => 'x12345678',
        'class' => 1, 'class_expire' => now()->addMonth(),
        'transfer_enable' => 10 * 1024 ** 3, 'u' => 0, 'd' => 0, 'banned' => false,
    ]);
    $node = \App\Models\Node::create([
        'name' => 'N', 'server' => 's', 'port' => 1, 'type' => 'vmess', 'net' => 'tcp',
        'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'PWSEC',
        'online' => true, 'enabled' => true, 'last_heartbeat' => time(),
    ]);

    $svc = app(\App\Services\NodeUserService::class);
    expect(collect($svc->servableUsers($node))->pluck('id')->contains($u->id))->toBeTrue();

    $u->update(['banned' => true]);
    \Cache::flush();   // `[!]` servableUsers 走 Cache::remember,不清会读到上一次的名单

    expect(collect($svc->servableUsers($node))->pluck('id')->contains($u->id))->toBeFalse();
});
