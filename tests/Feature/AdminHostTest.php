<?php

use App\Models\User;

/**
 * 方案 A：管理后台与用户面分成两个主机名，共用一条隧道、同一个应用。
 * admin.<域名> 整站套 Cloudflare Access；app.<域名> 不套(用户和节点要用)。
 *
 * `[!!]` 两个主机名指向同一个应用 —— 所以 app.<域名>/admin/* 本来是可达的,
 * 后台等于从侧门敞着。这组用例守的就是应用侧那道补挡。
 */
function adminUserForHost(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('未配 ADMIN_HOST 时后台照常可进', function () {
    config(['app.admin_host' => null]);

    $this->actingAs(adminUserForHost())->get('/admin')->assertOk();
});

it('配了 ADMIN_HOST 后从管理主机名可进', function () {
    config(['app.admin_host' => 'admin.example.test']);

    $this->actingAs(adminUserForHost())
        ->get('http://admin.example.test/admin')->assertOk();
});

// `[!!]` 这条是重点:用户面主机名【没有 Access】,后台必须在应用里挡住。
it('从用户面主机名进后台是 404', function () {
    config(['app.admin_host' => 'admin.example.test']);

    $this->actingAs(adminUserForHost())
        ->get('http://app.example.test/admin')->assertNotFound();
});

// `[!]` 404 而不是 403/302:后两者等于告诉探测者"这里确实有后台"。
// 而且没登录也要 404 —— 主机名检查排在 auth 前面。
it('未登录从用户面主机名进后台也是 404,不是跳登录', function () {
    config(['app.admin_host' => 'admin.example.test']);

    $this->get('http://app.example.test/admin/nodes')->assertNotFound();
});

it('主机名大小写不敏感', function () {
    config(['app.admin_host' => 'admin.example.test']);

    $this->actingAs(adminUserForHost())
        ->get('http://ADMIN.example.test/admin')->assertOk();
});

// AdminHost 只挡 /admin/*，不该碰用户页。
// `[!]` 这条守的是 AdminHost；用户【网页】能在哪个 host 打开是 WebOnOfficialHost 的事
//   (它把非官网域的网页跳回官网域)。所以让 app.example.test 当官网(用户面)域,
//   验配了 ADMIN_HOST 后 AdminHost 仍不拦用户面的 /login。
it('用户页不受后台主机名限制', function () {
    config(['app.admin_host' => 'admin.example.test', 'app.url' => 'http://app.example.test']);

    $this->get('http://app.example.test/login')->assertOk();
});
