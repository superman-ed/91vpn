<?php

use App\Models\HelpArticle;
use App\Models\User;

it('公开返回已发布帮助文档,隐藏草稿,含分类字段', function () {
    HelpArticle::create(['category' => '连接使用', 'title' => '连不上怎么办', 'content' => '换节点', 'published' => true, 'sort' => 5]);
    HelpArticle::create(['category' => '账号', 'title' => '这是草稿', 'content' => 'x', 'published' => false]);

    $res = $this->getJson('/api/help')->assertOk()->assertJsonPath('ret', 1);
    $titles = collect($res->json('data'))->pluck('title');
    expect($titles)->toContain('连不上怎么办');
    expect($titles)->not->toContain('这是草稿');
    expect($res->json('data.0'))->toHaveKeys(['id', 'category', 'title', 'content']);
});

it('游客(无 token)也能读帮助中心', function () {
    HelpArticle::create(['category' => '常见问题', 'title' => '公开可读', 'content' => 'y', 'published' => true]);
    $this->getJson('/api/help')->assertOk()->assertJsonPath('ret', 1);
});

it('管理员可新增/编辑/删除帮助文档', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post('/admin/help', ['category' => '常见问题', 'platform' => 'all', 'title' => '如何使用', 'content' => '内容', 'sort' => 1, 'published' => '1'])
        ->assertRedirect('/admin/help');
    $a = HelpArticle::firstWhere('title', '如何使用');
    expect($a)->not->toBeNull();

    $this->actingAs($admin)->put("/admin/help/{$a->id}", ['category' => '账号', 'platform' => 'android', 'title' => '改标题', 'content' => '新内容', 'sort' => 2, 'published' => '1'])
        ->assertRedirect('/admin/help');
    expect($a->fresh()->title)->toBe('改标题');

    $this->actingAs($admin)->delete("/admin/help/{$a->id}")->assertRedirect('/admin/help');
    expect(HelpArticle::firstWhere('title', '改标题'))->toBeNull();
});
