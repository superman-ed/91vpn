<?php

use App\Models\User;

/**
 * 后台「技术文档」页。文档正文是 sogacore/docs/guide 的副本
 * （deploy/sync-docs.sh 同步）。
 */
function docsAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('文档页能打开并渲染出正文', function () {
    $res = $this->actingAs(docsAdmin())->get('/admin/docs')->assertOk();

    // markdown 真的被渲染了(不是原样输出)
    expect($res->getContent())->toContain('<h1>')->toContain('技术文档');
});

// `[!]` 断言正文里的【特征词】而不是"中转"这种侧边栏里也有的词 ——
// 否则只渲染出外壳、正文是空的,用例照样绿。
it('能按篇切换,渲染的是正文不是外壳', function () {
    $this->actingAs(docsAdmin())->get('/admin/docs/05-relay')
        ->assertOk()
        ->assertSee('XUDP', false)              // 05 正文独有
        ->assertSee('PROXY protocol', false);

    $this->actingAs(docsAdmin())->get('/admin/docs/07-api-node')
        ->assertOk()
        ->assertSee('custom_config', false)     // 07 正文独有
        ->assertDontSee('XUDP', false);         // 且确实换了一篇
});

// `[!!]` slug 直接拼进路径的话 ../../.env 就能读到不该读的东西。
// 这里有两道:路由的 {slug?} 不匹配斜杠(带斜杠的直接 404),
// 控制器只接受目录里【真实存在】的文件名。两道都测。
it('带路径分隔的 slug 进不来', function () {
    $this->actingAs(docsAdmin())->get('/admin/docs/..%2F..%2F.env')->assertNotFound();
});

it('不存在的篇名回落到首页,不报错也不泄露', function () {
    $res = $this->actingAs(docsAdmin())->get('/admin/docs/no-such-doc')->assertOk();

    expect($res->getContent())->toContain('技术文档')->not->toContain('APP_KEY');
});

// `[!!]` 副本必然会过期,所以来源提交要显示出来 —— 静默过期的文档比没有文档
// 更坏,因为人会照着它做。
it('显示同步来源,让过期看得见', function () {
    $this->actingAs(docsAdmin())->get('/admin/docs')
        ->assertOk()->assertSee('同步自');
});

it('普通用户打不开', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get('/admin/docs')->assertForbidden();
});
