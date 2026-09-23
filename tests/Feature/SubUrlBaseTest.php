<?php

use App\Models\User;
use App\Services\ServiceReadiness;
use App\Support\ClientLinks;

/**
 * 订阅域名与面板域名分离。
 *
 * `[!!]` 为什么要分：订阅 URL 是每个用户的客户端【每天都要访问】的东西。
 * 与面板同域时，面板域名一旦被封或被污染，用户不只是打不开网页 ——
 * 是【连订阅也拉不了】：换不了节点、加不了新设备。
 * `[D]` 对照 91jcdy：`sub.91jcdy.com` 专用域名 + WAF，主域 `91jcdy.com` 不解析。
 *
 * `[!!]` 而订阅 URL 一旦发出去就【收不回来】—— 它嵌在每个人的客户端配置里。
 * 所以这件事必须在没有用户的时候定下来。
 */
it('留空时跟随 APP_URL —— 与分离之前完全一致', function () {
    config(['app.sub_url_base' => '']);
    $u = User::factory()->create();

    expect(ClientLinks::subUrl($u))->toBe(url('/sub/'.$u->invite_token));
});

it('配了就用它', function () {
    config(['app.sub_url_base' => 'https://sub.example.com']);
    $u = User::factory()->create();

    expect(ClientLinks::subUrl($u))->toBe('https://sub.example.com/sub/'.$u->invite_token);
});

// `[!]` 末尾斜杠是最容易手滑的一个字符，而它会拼出 //sub/xxx
it('末尾斜杠被吃掉，不会拼出双斜杠', function () {
    config(['app.sub_url_base' => 'https://sub.example.com/']);
    $u = User::factory()->create();

    expect(ClientLinks::subUrl($u))->toBe('https://sub.example.com/sub/'.$u->invite_token);
});

// `[!!]` 三处调用必须走同一个方法 —— 分离之前它们【各拼各的】，
// 而"各拼各的"意味着改域名时必然漏掉一处，且漏的那处不会报错、只是发错地址。
it('网页端与客户端 API 拿到的是同一个地址', function () {
    config(['app.sub_url_base' => 'https://sub.example.com']);
    $u = User::factory()->create();

    // `[!]` 路由是 GET /api/node，出参在 data.sub_url。
    //   第一版写成 /api/node/settings 并加了 if ($api !== null) 兜底 ——
    //   端点不存在时那条断言会【静默跳过】，用例照样绿。有条件的断言等于没断言。
    $web = ClientLinks::for($u)['subUrl'];
    $api = $this->withHeader('Authorization', 'Bearer '.$u->api_token)
        ->getJson('/api/node')->assertOk()->json('data.sub_url');

    expect($web)->toBe('https://sub.example.com/sub/'.$u->invite_token);
    expect($api)->toBe($web);
});

// 自检页：同域时给 warn（不是 bad —— 它不影响现在能不能用，只影响以后改起来贵不贵）
it('订阅与面板同域时自检给 warn', function () {
    config(['app.url' => 'https://app.example.com', 'app.sub_url_base' => '']);

    $c = collect(app(ServiceReadiness::class)->check())
        ->firstWhere('title', '订阅地址');

    expect($c['level'])->toBe('warn');
    expect($c['detail'])->toContain('同一个可注册域');
});

it('订阅与面板不同域时自检给 ok', function () {
    config(['app.url' => 'https://app.example.com', 'app.sub_url_base' => 'https://sub.other.net']);

    $c = collect(app(ServiceReadiness::class)->check())
        ->firstWhere('title', '订阅地址');

    expect($c['level'])->toBe('ok');
});
