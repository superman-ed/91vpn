<?php

use App\Models\User;

it('shows one-click import buttons on node page', function () {
    $user = User::factory()->create(['invite_token' => 'IMPORTTOKEN']);
    $res = $this->actingAs($user)->get('/user/downloads');
    $res->assertOk();
    // Clash 一键导入 scheme
    $res->assertSee('clash://install-config', false);
    // Shadowrocket scheme
    $res->assertSee('shadowrocket://', false);
    // 订阅链接
    $res->assertSee('/sub/IMPORTTOKEN');
});
