<?php

use App\Models\User;

/**
 * 站点设置页顶部的「域名与地址」只读块。
 *
 * `[!!]` 为什么要有它：自检全绿时整块【不显示】（刻意的 —— 常年绿着的横幅会被
 * 当成装饰）。于是订阅域名一旦配对，运维在界面上就【无处确认它被配成了什么】，
 * 只能 SSH 进去 grep。而"看"和"改"是两件事。
 *
 * `[!]` 只读、不给输入框：订阅是用户唯一的入口，填错一个字符就是全员连不上；
 * 且放数据库的话，从旧备份恢复一次会让它静默退回旧值。
 */
function adiAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('显示三个地址，并标出订阅是否与面板分开', function () {
    config(['app.url' => 'https://app.example.com', 'app.sub_url_base' => 'https://sub.other.net']);

    $html = $this->actingAs(adiAdmin())->get('/admin/settings')->assertOk()->getContent();

    expect($html)->toContain('域名与地址');
    expect($html)->toContain('https://app.example.com');
    expect($html)->toContain('https://sub.other.net');
    expect($html)->toContain('已与面板分开');
});

it('未单独配置时显示回退提示', function () {
    config(['app.url' => 'https://app.example.com', 'app.sub_url_base' => '']);

    $html = $this->actingAs(adiAdmin())->get('/admin/settings')->assertOk()->getContent();

    expect($html)->toContain('跟随面板域名');
    expect($html)->toContain('最便宜的时刻');   // 提示要说明"现在改最便宜"
});

// `[!!]` 只读 —— 不能出现可编辑的输入框，否则就是把"全员连不上"的开关放到了
// 一次手滑可及的地方。
it('不给输入框', function () {
    config(['app.sub_url_base' => 'https://sub.other.net']);

    $html = $this->actingAs(adiAdmin())->get('/admin/settings')->assertOk()->getContent();

    expect($html)->not->toContain('name="sub_url_base"');
    expect($html)->not->toContain('name="app_url"');
    expect($html)->toContain('在服务器 .env 里改');
});
