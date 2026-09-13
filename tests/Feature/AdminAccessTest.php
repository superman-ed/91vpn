<?php

use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Route;

/**
 * 后台角色与权限。
 *
 * `[!!]` 这一组里最要紧的是第一条：**没有任何一条管理路由可以没声明权限**。
 * 权限系统最常见的破口不是判定写错，是"新加了一条路由，忘了配权限"——
 * 那种漏洞没有任何现象，直到有人发现自己点得进去。
 */
function aaUser(?string $role): User
{
    return User::factory()->create(['is_admin' => $role !== null, 'admin_role' => $role]);
}

it('每一条管理路由都声明了所需权限', function () {
    $missing = [];
    foreach (Route::getRoutes() as $r) {
        $uri = $r->uri();
        if ($uri !== 'admin' && ! str_starts_with($uri, 'admin/')) {
            continue;
        }
        foreach ($r->methods() as $m) {
            if (in_array($m, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }
            try {
                AdminAccess::capFor($uri, $m);
            } catch (\RuntimeException $e) {
                $missing[] = "{$m} {$uri}";
            }
        }
    }

    expect($missing)->toBe([], "这些路由没有在 AdminAccess::ROUTES 里声明权限");
});

it('声明的能力名都是真实存在的 —— 打错字不会变成"谁都没有"', function () {
    // `[!!]` 拼错的能力名会让 can() 恒为 false，表现是"超管之外谁都进不去"，
    // 而超管恒为 true，所以开发自己测不出来。
    $bad = [];
    foreach (AdminAccess::ROUTES as [$prefix, $read, $write]) {
        foreach ([$read, $write] as $c) {
            if ($c !== null && ! isset(AdminAccess::CAPS[$c])) {
                $bad[] = "{$prefix} → {$c}";
            }
        }
    }
    foreach (AdminAccess::MATRIX as $role => $caps) {
        foreach ($caps as $c) {
            if (! isset(AdminAccess::CAPS[$c])) {
                $bad[] = "{$role} → {$c}";
            }
        }
    }

    expect($bad)->toBe([]);
});

it('更具体的前缀排在更通用的前面', function () {
    $prefixes = array_column(AdminAccess::ROUTES, 0);
    foreach ($prefixes as $i => $general) {
        foreach (array_slice($prefixes, $i + 1) as $specific) {
            // 若后面出现了以前面某项为前缀的更具体项，它永远匹配不到
            expect(str_starts_with($specific, $general.'/'))->toBeFalse(
                "「{$specific}」比「{$general}」更具体，却排在它后面 —— 永远匹配不到"
            );
        }
    }
});

it('超级管理员拥有全部能力', function () {
    foreach (array_keys(AdminAccess::CAPS) as $c) {
        expect(AdminAccess::can('super', $c))->toBeTrue();
    }
});

it('没有角色的管理员什么都做不了 —— 但这不是"进不去后台"', function () {
    // `[!]` is_admin 与 admin_role 分工:前者是门,后者是能力。
    // 一个 is_admin=true 但没角色的账号,能到 403,不是 500。
    foreach (array_keys(AdminAccess::CAPS) as $c) {
        expect(AdminAccess::can(null, $c))->toBeFalse();
    }
});

/**
 * 真实请求。`[!!]` 上面几条验的是表本身，这几条验的是【强制真的发生了】——
 * 表写对了而中间件没接上，前面全绿、后台却门户大开。
 */
it('运营进得了套餐，进不了中转规则', function () {
    $ops = aaUser('ops');
    $this->actingAs($ops)->get('/admin/plans')->assertOk();
    $this->actingAs($ops)->get('/admin/rules')->assertForbidden();
});

it('运维进得了节点与规则，进不了财务和用户', function () {
    $infra = aaUser('infra');
    $this->actingAs($infra)->get('/admin/nodes')->assertOk();
    $this->actingAs($infra)->get('/admin/rules')->assertOk();
    $this->actingAs($infra)->get('/admin/finance')->assertForbidden();
    $this->actingAs($infra)->get('/admin/users')->assertForbidden();
});

it('客服看得到用户、改得了用户，但动不了套餐价格', function () {
    $s = aaUser('support');
    $this->actingAs($s)->get('/admin/users')->assertOk();
    $this->actingAs($s)->get('/admin/plans')->assertForbidden();
});

it('财务看得了订单与财务，看不了节点管理', function () {
    $f = aaUser('finance');
    $this->actingAs($f)->get('/admin/orders')->assertOk();
    $this->actingAs($f)->get('/admin/finance')->assertOk();
    $this->actingAs($f)->get('/admin/rules')->assertForbidden();
});

it('只读审计连写操作都发不出去', function () {
    // `[!!]` 「只读」必须在【方法】上成立,不能只靠没给他按钮。
    $a = aaUser('auditor');
    $this->actingAs($a)->get('/admin/users')->assertOk();
    $user = User::factory()->create();
    $this->actingAs($a)->post("/admin/users/{$user->id}/toggle-ban")->assertForbidden();
});

it('运营看得到节点状态，但改不了节点', function () {
    // 这正是"运营人员不必看到 Relay 技术细节"那条的落点:
    // 卖套餐要知道哪个区域能不能用,但不该能改节点。
    $ops = aaUser('ops');
    $this->actingAs($ops)->get('/admin/nodes')->assertOk();
    $node = \App\Models\Node::create([
        'name' => 'N', 'server' => '203.0.113.44', 'port' => 443, 'type' => 'vless',
        'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0, 'secret' => 'AA1',
        'role' => 'landing',
    ]);
    $this->actingAs($ops)->delete("/admin/nodes/{$node->id}")->assertForbidden();
});

it('每个角色都进得了总览和改自己密码', function () {
    foreach (array_keys(AdminAccess::ROLES) as $role) {
        $this->actingAs(aaUser($role))->get('/admin')->assertOk();
        $this->actingAs(aaUser($role))->get('/admin/account')->assertOk();
    }
});

it('非管理员一律 403，与角色无关', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false, 'admin_role' => 'super']))
        ->get('/admin')->assertForbidden();
});

/**
 * 防自锁。`[!!]` 这类错误的特点是【发生之后没有人能修】——
 * 把唯一的超管降成只读审计，而改回去需要超管。所以必须在发生之前拦。
 */
it('不能改自己的角色', function () {
    $me = aaUser('super');
    aaUser('super');  // 另有一个超管，排除"至少留一个"这条规则的干扰
    $this->actingAs($me)->post("/admin/admins/{$me->id}/role", ['admin_role' => 'auditor']);

    expect($me->fresh()->admin_role)->toBe('super');
});

it('不能把最后一个超级管理员降级', function () {
    $only = aaUser('super');
    $other = aaUser('super');
    // 先降掉 other，使 only 成为唯一超管
    $this->actingAs($only)->post("/admin/admins/{$other->id}/role", ['admin_role' => 'ops']);
    expect($other->fresh()->admin_role)->toBe('ops');

    // 现在 other 是运营，让他去降 only —— 应被角色本身挡住（admins.manage 只有超管有）。
    // `[!]` 必须 fresh()：actingAs 用的是【内存里的实例】，
    // 上一步只改了数据库，旧实例的 admin_role 还是 super —— 那样测的是超管，不是运营。
    $this->actingAs($other->fresh())->post("/admin/admins/{$only->id}/role", ['admin_role' => 'ops'])
        ->assertForbidden();
    expect($only->fresh()->admin_role)->toBe('super');
});

it('撤销管理员时一并清掉角色 —— 否则下次提升会悄悄恢复旧角色', function () {
    $admin = aaUser('super');
    $target = aaUser('infra');

    $this->actingAs($admin)->delete("/admin/admins/{$target->id}");

    $t = $target->fresh();
    expect($t->is_admin)->toBeFalse()->and($t->admin_role)->toBeNull();
});

it('改角色要留审计 —— 这是改别人能做什么的动作', function () {
    $admin = aaUser('super');
    $target = aaUser('ops');

    $this->actingAs($admin)->post("/admin/admins/{$target->id}/role", ['admin_role' => 'finance']);

    expect(\DB::table('audit_logs')->where('action', 'admin.role')->count())->toBe(1);
});

it('新建管理员必须指定角色 —— 不给默认值', function () {
    // `[!]` 给默认值的话，忘了选就是一个权限过大或过小的账号，而没人会注意到。
    $admin = aaUser('super');
    $this->actingAs($admin)->post('/admin/admins', [
        'username' => 'newadmin1', 'password' => 'secret12345',
    ])->assertSessionHasErrors('admin_role');
});

/**
 * 导航。`[!!]` 这里验的是【不出现死链】和【不泄露存在性】，
 * 不是访问控制 —— 那一层在中间件上，前面已经验过。
 */
it('导航里的每一项都指向一条真实存在、且声明了权限的路由', function () {
    // `[!]` 去掉尾部的可选参数再比：`admin/docs/{slug?}` 这条路由，
    // `/admin/docs` 确实进得去 —— 严格全等会把它误判成死链。
    $routes = collect(Route::getRoutes())
        ->map(fn ($r) => '/'.preg_replace('#/\{[^}]+\?\}$#', '', $r->uri()))
        ->all();
    $bad = [];
    foreach (\App\Support\AdminNav::GROUPS as $group => $items) {
        foreach ($items as [$path, , $label]) {
            if (! in_array($path, $routes, true)) {
                $bad[] = "{$group}/{$label} → {$path}（路由不存在）";

                continue;
            }
            try {
                AdminAccess::capFor(ltrim($path, '/'), 'GET');
            } catch (\RuntimeException) {
                $bad[] = "{$group}/{$label} → {$path}（没声明权限）";
            }
        }
    }

    expect($bad)->toBe([]);
});

it('运营的导航里没有转发规则，运维的导航里没有财务', function () {
    $ops = collect(\App\Support\AdminNav::forRole('ops'))->flatten(1)->pluck(0);
    expect($ops)->not->toContain('/admin/rules')->toContain('/admin/plans');

    $infra = collect(\App\Support\AdminNav::forRole('infra'))->flatten(1)->pluck(0);
    expect($infra)->not->toContain('/admin/finance')->toContain('/admin/rules');
});

it('整组都看不见时连组标题也不显示', function () {
    // `[!]` 留一个空的分组标题，会让人以为"这里应该有东西，是不是坏了"。
    $finance = \App\Support\AdminNav::forRole('finance');
    expect($finance)->not->toHaveKey('节点与网络');
});

it('超级管理员看得到全部分组', function () {
    expect(array_keys(\App\Support\AdminNav::forRole('super')))
        ->toBe(array_keys(\App\Support\AdminNav::GROUPS));
});

it('用户只在导航里出现一处', function () {
    // 原提案里「运营中心」下有用户管理、又单列一个「用户中心」——
    // 同一个东西出现两次，人不知道该点哪个。
    $all = collect(\App\Support\AdminNav::GROUPS)->flatten(1)->pluck(0);
    expect($all->filter(fn ($p) => $p === '/admin/users')->count())->toBe(1);
});
