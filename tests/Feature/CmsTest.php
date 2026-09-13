<?php

use App\Models\Banner;
use App\Models\ClientDownload;
use App\Models\User;

/**
 * 客户端下载 + 首页 Banner。
 *
 * `[!!]` 这两块的价值只有一条：**改文案和链接不该找开发**。
 * 所以用例守的是"后台改完，用户页立刻变"，以及几个会静默出错的地方。
 */
function cmsAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function cmsUser(): User
{
    return User::factory()->create(['class' => 1, 'class_expire' => now()->addDays(30)]);
}

// ── 客户端下载 ──

it('迁移把原来硬编码的四个平台原样搬了进来 —— 上线当天页面一字不变', function () {
    expect(ClientDownload::count())->toBe(4);
    expect(ClientDownload::pluck('platform')->all())
        ->toBe(['Windows', 'macOS', 'Android', 'iOS']);
    // 链接仍为空 = 「即将推出」，与改造前完全一致
    expect(ClientDownload::whereNotNull('url')->count())->toBe(0);
});

it('后台填了链接，用户页立刻就能下载', function () {
    ClientDownload::where('platform', 'Windows')
        ->update(['url' => 'https://dl.example.com/91vpn.exe', 'version' => '1.2.0']);

    $this->actingAs(cmsUser())->get('/user/downloads')->assertOk()
        ->assertSee('https://dl.example.com/91vpn.exe', false)
        ->assertSee('v1.2.0');
});

it('链接为空的平台仍然列出来，显示「即将推出」', function () {
    // `[!!]` 空链接是一个【有意义的状态】，不是缺数据。
    // 把它整个藏掉，用户会以为我们不支持这个平台。
    $this->actingAs(cmsUser())->get('/user/downloads')->assertOk()
        ->assertSee('macOS')->assertSee('即将推出');
});

it('停用的平台不出现在用户页', function () {
    ClientDownload::where('platform', 'iOS')->update(['enabled' => false]);

    $html = $this->actingAs(cmsUser())->get('/user/downloads')->assertOk()->getContent();
    expect($html)->not->toContain('91VPN For iOS');
});

it('下载链接必须是 http(s) 的完整地址', function () {
    // `[!!]` 这个字段会被渲染成 <a href> —— 放任相对路径或 javascript:
    // 就等于开了一个人人可点的注入位。
    foreach (['javascript:alert(1)', '/relative/path', 'ftp://x.example/a'] as $bad) {
        $this->actingAs(cmsAdmin())->post('/admin/downloads', [
            'platform' => 'X', 'label' => 'X', 'icon' => 'fas fa-x', 'sort' => 0,
            'url' => $bad,
        ])->assertSessionHasErrors('url');
    }
    expect(ClientDownload::where('platform', 'X')->exists())->toBeFalse();
});

it('改下载链接要留审计，且写清改成了什么', function () {
    $d = ClientDownload::where('platform', 'Windows')->first();
    $this->actingAs(cmsAdmin())->put("/admin/downloads/{$d->id}", [
        'platform' => 'Windows', 'label' => '91VPN For Windows', 'icon' => 'fab fa-windows',
        'sort' => 1, 'url' => 'https://dl.example.com/a.exe', 'enabled' => '1',
    ])->assertRedirect();

    $log = \DB::table('audit_logs')->where('action', 'download.update')->first();
    expect($log)->not->toBeNull()
        ->and($log->detail ?? $log->description ?? '')->toContain('https://dl.example.com/a.exe');
});

// ── Banner ──

it('展示中的 Banner 出现在用户首页', function () {
    Banner::create(['title' => '双十一五折', 'text' => '限时优惠', 'sort' => 0, 'enabled' => true]);

    $this->actingAs(cmsUser())->get('/user')->assertOk()->assertSee('双十一五折');
});

it('过了结束时间的 Banner 自动不再展示 —— 不靠人记得下架', function () {
    // `[!!]` "忘了下架"是运营最常见的失误，且没有任何现象提醒。
    Banner::create(['title' => '已经结束的活动', 'sort' => 0, 'enabled' => true,
        'starts_at' => now()->subDays(10), 'ends_at' => now()->subDay()]);

    $html = $this->actingAs(cmsUser())->get('/user')->assertOk()->getContent();
    expect($html)->not->toContain('已经结束的活动');
});

it('还没到开始时间的 Banner 不提前露出', function () {
    Banner::create(['title' => '还没开始的活动', 'sort' => 0, 'enabled' => true,
        'starts_at' => now()->addDay()]);

    $html = $this->actingAs(cmsUser())->get('/user')->assertOk()->getContent();
    expect($html)->not->toContain('还没开始的活动');
});

it('后台列表要说清楚一条 Banner 为什么没在展示', function () {
    // `[!]` 只标一个"未展示"的话，人会去查图片、链接、缓存，
    // 而真因往往只是时间写反了。
    Banner::create(['title' => '过期的', 'sort' => 0, 'enabled' => true,
        'starts_at' => now()->subDays(5), 'ends_at' => now()->subDay()]);
    Banner::create(['title' => '停用的', 'sort' => 1, 'enabled' => false]);

    $this->actingAs(cmsAdmin())->get('/admin/banners')->assertOk()
        ->assertSee('已过结束时间')->assertSee('已停用');
});

it('结束时间早于开始时间要被拦下 —— 否则它永远不显示且毫无提示', function () {
    $this->actingAs(cmsAdmin())->post('/admin/banners', [
        'title' => '时间写反了', 'sort' => 0,
        'starts_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
        'ends_at' => now()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('ends_at');

    expect(Banner::where('title', '时间写反了')->exists())->toBeFalse();
});

it('Banner 的图片和跳转链接同样只收 http(s)', function () {
    foreach (['image_url', 'link'] as $field) {
        $this->actingAs(cmsAdmin())->post('/admin/banners', [
            'title' => 'X', 'sort' => 0, $field => 'javascript:alert(1)',
        ])->assertSessionHasErrors($field);
    }
});

it('内容这两页运营进得去，运维进不去', function () {
    $ops = User::factory()->create(['is_admin' => true, 'admin_role' => 'ops']);
    $infra = User::factory()->create(['is_admin' => true, 'admin_role' => 'infra']);

    $this->actingAs($ops)->get('/admin/downloads')->assertOk();
    $this->actingAs($ops)->get('/admin/banners')->assertOk();
    $this->actingAs($infra)->get('/admin/downloads')->assertForbidden();
});
