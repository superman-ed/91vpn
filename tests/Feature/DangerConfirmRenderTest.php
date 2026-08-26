<?php
use App\Models\Node;
use App\Models\User;

it('renders admin pages with the danger-confirm component', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Node::create(['name' => 'HK01', 'server' => 'x', 'port' => 1, 'secret' => 's']);
    $target = User::factory()->create();

    $this->actingAs($admin)->get('/admin/nodes')->assertOk()->assertSee('data-dgr-word="HK01"', false);
    $this->actingAs($admin)->get('/admin/admins')->assertOk();
    $this->actingAs($admin)->get("/admin/users/{$target->id}/edit")->assertOk()->assertSee('data-orig-money', false);
    $this->actingAs($admin)->get('/admin/nodes')->assertSee('dcOverlay', false);   // 全局确认组件已注入
});
