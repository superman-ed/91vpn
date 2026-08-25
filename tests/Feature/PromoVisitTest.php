<?php

use App\Models\PromoChannel;

it('counts PV on every visit and UV once per session', function () {
    $ch = PromoChannel::create(['code' => 'VIS', 'name' => 'x', 'enabled' => true]);

    // 同一 session 连点 3 次 → PV=3, UV=1
    $this->get('/login?ch=VIS');
    $this->get('/login?ch=VIS');
    $this->get('/login?ch=VIS');

    $ch->refresh();
    expect($ch->pv)->toBe(3);
    expect($ch->uv)->toBe(1);
});

it('counts a fresh session as a new UV', function () {
    $ch = PromoChannel::create(['code' => 'VIS2', 'name' => 'x', 'enabled' => true]);

    $this->get('/login?ch=VIS2');
    $this->flushSession();          // 模拟另一个访客(新 session)
    $this->get('/login?ch=VIS2');

    $ch->refresh();
    expect($ch->pv)->toBe(2);
    expect($ch->uv)->toBe(2);
});

it('does not count visits for a disabled or unknown code', function () {
    $off = PromoChannel::create(['code' => 'OFF', 'name' => 'x', 'enabled' => false]);

    $this->get('/login?ch=OFF');    // 停用
    $this->get('/login?ch=NOPE');   // 不存在

    expect($off->fresh()->pv)->toBe(0);
});
