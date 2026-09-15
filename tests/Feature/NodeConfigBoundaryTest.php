<?php

use App\Models\ForwardOutbound;
use App\Models\ForwardRule;
use App\Models\Node;
use App\Models\User;
use App\Services\ForwardRuleService;

/**
 * 下发给节点的配置里，不得出现【用户维度】的任何东西。
 *
 * 这条边界不是新定的，是把既有架构决策钉住：
 *
 *   D-1  中转不认证用户。
 *   D-2  inbound_cred / out_cred 是面板铸造的【节点间凭据】(Relay ↔ Landing)，
 *        不是用户凭据，因此【允许】出现在 compileForNode() 的输出里。
 *
 * 另外两条既有约束也一并钉住：
 *
 *   · 入站 REALITY private_key 【允许】—— 中转要伪装成 TLS 站点就必须终结 TLS，
 *     终结 TLS 就必须持有私钥。这是物理约束，不是设计缺陷。
 *   · 出站 REALITY private_key 【禁止】—— 拨号方只需要 public_key/short_id/server_names。
 *     ForwardRuleService 已经在编译期主动 unset 它，防的是管理员误填。
 *
 * `[!!]` 判据【不能】是「配置里有没有出现 uuid 这个键」——
 * 节点间凭据里合法地就有 uuid。所以这里按两个维度查：
 *   (a) 路径：private_key 只允许在 inbound.reality 下
 *   (b) 取值：把库里真实用户的 uuid/passwd/token 拿出来，逐个叶子比对
 * 少了 (b)，一个把用户 uuid 塞进 credential 的改动会完全测不出来。
 */

/** 把嵌套结构摊平成「路径 => 叶子值」，路径形如 rules[0].outbounds[1].reality.private_key */
function fnFlatten(mixed $v, string $prefix = ''): array
{
    if (is_object($v)) {
        $v = (array) $v;
    }
    if (! is_array($v)) {
        return [$prefix => $v];
    }
    $out = [];
    foreach ($v as $k => $item) {
        $path = is_int($k) ? "{$prefix}[{$k}]" : ($prefix === '' ? (string) $k : "{$prefix}.{$k}");
        $out += fnFlatten($item, $path);
    }

    return $out;
}

$GLOBALS['nc'] = 0;

/** 造一条【尽量丰满】的规则：REALITY 入站 + 节点间凭据 + 出站里混入误填的私钥 */
function ncFixture(): array
{
    $i = ++$GLOBALS['nc'];
    $mk = fn (string $role, string $ip) => Node::create([
        'name' => $role.$i, 'server' => $ip.$i, 'port' => 39500,
        'type' => 'vless', 'net' => 'tcp', 'traffic_rate' => 1, 'node_class' => 0,
        'secret' => 'NC'.$role.$i, 'role' => $role,
        'enabled' => true, 'online' => true, 'last_heartbeat' => time() - 5,
    ]);
    $relay = $mk('relay', '198.51.100.');
    $landing = $mk('landing', '203.0.113.');

    $rule = ForwardRule::create([
        'name' => '规则'.$i, 'enabled' => true,
        'listen_port' => (string) (43000 + $i),
        'inbound_node_set' => [$relay->id],
        'inbound_type' => 'vless', 'inbound_security' => 'reality',
        'balance' => 'roundrobin', 'backup_balance' => 'fallback', 'hc_enabled' => true,
        // D-2：节点间凭据 —— 允许下发
        'inbound_cred' => ['uuid' => 'NODE-TO-NODE-CRED-'.$i, 'flow' => 'xtls-rprx-vision'],
        // 入站 REALITY 私钥 —— 伪装所需，允许下发
        'inbound_opts' => ['reality' => [
            'private_key' => 'INBOUND-PRIV-'.$i,
            'dest' => 'www.apple.com:443',
            'server_names' => ['www.apple.com'],
            'short_ids' => [''],
        ]],
    ]);

    ForwardOutbound::create([
        'rule_id' => $rule->id, 'pool' => 'primary', 'enabled' => true,
        'out_type' => 'vless', 'trusted_transit' => true,
        'target_node_set' => [$landing->id],
        'out_cred' => ['uuid' => 'DIAL-CRED-'.$i],
        // `[!!]` 故意把私钥填进【出站】—— 这正是编译期该剥掉的东西
        'out_opts' => ['reality' => [
            'private_key' => 'OUTBOUND-PRIV-SHOULD-BE-STRIPPED-'.$i,
            'public_key' => 'PUB-'.$i,
            'short_id' => '',
        ]],
    ]);

    return [$relay, $rule];
}

it('compileForNode 不泄漏用户凭据，也不下发出站 REALITY 私钥', function () {
    // 库里放几个真实用户 —— 取值比对要拿它们当靶子
    $users = User::factory()->count(3)->create();
    [$relay] = ncFixture();

    $compiled = app(ForwardRuleService::class)->compileForNode($relay);

    // 前置条件：确实编译出了东西，否则下面全是空真
    expect($compiled['rules'])->not->toBe([]);
    $flat = fnFlatten($compiled['rules'], 'rules');
    expect(count($flat))->toBeGreaterThan(20);

    $leaks = [];

    // ── (a) 路径维度：private_key 只允许在 inbound.reality 下 ──────
    foreach ($flat as $path => $val) {
        if (! str_contains($path, 'private_key')) {
            continue;
        }
        if (! preg_match('/^rules\[\d+\]\.inbound\.reality\.private_key$/', $path)) {
            $leaks[] = "出站/其它位置出现 REALITY 私钥：{$path}";
        }
    }

    // ── (b) 路径维度：用户维度的字段名一律不得出现 ────────────────
    // `[!!]` 名单从 users 表【实际列】推导，不手写 —— 将来加了用户字段会自动纳入。
    // 扣掉与节点配置天然重名的那几个（它们在配置里指的是规则/节点，不是用户）。
    $userCols = \Illuminate\Support\Facades\Schema::getColumnListing('users');
    $collide = ['id', 'name', 'uuid', 'password', 'enabled', 'created_at', 'updated_at', 'remember_token'];
    $userOnly = array_diff($userCols, $collide);
    foreach ($flat as $path => $val) {
        $leaf = strtolower((string) preg_replace('/^.*\./', '', $path));
        if (in_array($leaf, array_map('strtolower', $userOnly), true)) {
            $leaks[] = "出现用户维度字段：{$path}";
        }
    }

    // ── (c) 取值维度：任何叶子都不得等于真实用户的敏感取值 ──────────
    // 这一条是关键：credential.uuid 这个【键】是合法的，
    // 但它的【值】绝不能是某个用户的 uuid。只查键名会完全漏掉这种改动。
    $secrets = [];
    foreach ($users as $u) {
        foreach (['uuid', 'passwd', 'email', 'api_token', 'invite_token', 'ref_code'] as $f) {
            $v = $u->getAttribute($f);
            if (is_string($v) && $v !== '') {
                $secrets[$v] = "用户#{$u->id}.{$f}";
            }
        }
    }
    expect($secrets)->not->toBe([]);       // 靶子必须存在，否则这一段是空真
    foreach ($flat as $path => $val) {
        if (is_string($val) && isset($secrets[$val])) {
            $leaks[] = "{$path} 的值等于 {$secrets[$val]}";
        }
    }

    expect($leaks)->toBe([]);
});

it('D-2：节点间凭据【允许】下发 —— 不要把它当用户凭据误杀', function () {
    [$relay, $rule] = ncFixture();
    $flat = fnFlatten(app(ForwardRuleService::class)->compileForNode($relay)['rules'], 'rules');

    // 入站的节点间凭据确实在配置里，且值就是面板铸造的那个
    $inCred = collect($flat)->filter(fn ($v, $k) => preg_match('/^rules\[\d+\]\.inbound\.credential\.uuid$/', $k));
    expect($inCred->values()->all())->toBe([$rule->inbound_cred['uuid']]);

    // 出站的拨号凭据同理（D-2 同一类：Relay → Landing，不是用户）
    $outCred = collect($flat)->filter(fn ($v, $k) => preg_match('/^rules\[\d+\]\.outbounds\[\d+\]\.credential\.uuid$/', $k));
    expect($outCred->count())->toBe(1);
});

it('入站 REALITY 私钥【允许】下发 —— 终结 TLS 就必须持有它', function () {
    [$relay, $rule] = ncFixture();
    $flat = fnFlatten(app(ForwardRuleService::class)->compileForNode($relay)['rules'], 'rules');

    $in = collect($flat)->filter(fn ($v, $k) => preg_match('/^rules\[\d+\]\.inbound\.reality\.private_key$/', $k));
    expect($in->values()->all())->toBe([$rule->inbound_opts['reality']['private_key']]);
});

it('出站 REALITY 私钥【被剥离】—— 即便管理员误填也不会下发', function () {
    [$relay] = ncFixture();
    $flat = fnFlatten(app(ForwardRuleService::class)->compileForNode($relay)['rules'], 'rules');

    // 反向对照：出站的 reality 块确实下发了（public_key 在），只是私钥被摘了
    $pub = collect($flat)->filter(fn ($v, $k) => str_contains($k, 'outbounds') && str_contains($k, 'reality.public_key'));
    expect($pub->count())->toBe(1);

    $priv = collect($flat)->filter(fn ($v, $k) => str_contains($k, 'outbounds') && str_contains($k, 'private_key'));
    expect($priv->keys()->all())->toBe([]);
});

it('摊平函数本身是可信的 —— 否则上面四条全是空真', function () {
    $flat = fnFlatten(['a' => ['b' => [1, 2], 'c' => ['d' => 'x']]], 'root');
    expect($flat)->toBe([
        'root.a.b[0]' => 1,
        'root.a.b[1]' => 2,
        'root.a.c.d' => 'x',
    ]);
});
