<?php

use App\Models\DailyTraffic;
use App\Models\Payback;
use App\Models\SubscribeLog;
use App\Models\User;
use App\Models\UserNotification;

const MISC_AUTH = ['Authorization' => 'Bearer TESTTOKEN123'];

// ---- 连接凭证 ----

it('returns connection credentials', function () {
    apiUser();
    $res = $this->getJson('/api/node', MISC_AUTH)->assertOk()->assertJsonPath('ret', 1);
    expect($res->json('data.sub_token'))->toBe('SUBTOKEN32');
    expect($res->json('data.sub_url'))->toContain('/sub/SUBTOKEN32');
    expect($res->json('data.uuid'))->not->toBeEmpty();
});

it('resets the subscription token', function () {
    apiUser();
    $res = $this->postJson('/api/node/reset-sub', [], MISC_AUTH)->assertOk()->assertJsonPath('ret', 1);
    expect($res->json('data.sub_token'))->not->toBe('SUBTOKEN32');
    expect(User::first()->invite_token)->not->toBe('SUBTOKEN32');
});

it('resets the connection credential (uuid + passwd)', function () {
    $u = apiUser();
    $oldUuid = $u->uuid;
    $res = $this->postJson('/api/node/reset-credential', [], MISC_AUTH)->assertOk()->assertJsonPath('ret', 1);
    expect($res->json('data.uuid'))->not->toBe($oldUuid);
    expect(User::first()->uuid)->not->toBe($oldUuid);
});

// ---- 流量 / 订阅日志 ----

it('returns daily traffic with total', function () {
    $u = apiUser();
    DailyTraffic::create(['user_id' => $u->id, 'date' => now()->toDateString(), 'u' => 100, 'd' => 200]);
    $res = $this->getJson('/api/traffic', MISC_AUTH)->assertOk();
    expect($res->json('data.total'))->toBe(300);
    expect($res->json('data.records.0.total'))->toBe(300);
});

it('returns subscribe logs', function () {
    $u = apiUser();
    SubscribeLog::create(['user_id' => $u->id, 'type' => 'clash', 'ip' => '1.2.3.4', 'location' => '香港', 'client' => 'clash', 'fetched_at' => now()]);
    $res = $this->getJson('/api/subscribe-log', MISC_AUTH)->assertOk();
    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.type'))->toBe('clash');
});

// ---- 站内信 ----

it('lists messages with unread count and marks read', function () {
    $u = apiUser();
    $n1 = UserNotification::create(['user_id' => $u->id, 'title' => 'A', 'content' => 'a']);
    UserNotification::create(['user_id' => $u->id, 'title' => 'B', 'content' => 'b']);
    $res = $this->getJson('/api/messages', MISC_AUTH)->assertOk();
    expect($res->json('data.unread'))->toBe(2);
    expect($res->json('data.messages'))->toHaveCount(2);

    $this->postJson("/api/messages/{$n1->id}/read", [], MISC_AUTH)->assertOk();
    expect($this->getJson('/api/messages', MISC_AUTH)->json('data.unread'))->toBe(1);

    $this->postJson('/api/messages/read-all', [], MISC_AUTH)->assertOk();
    expect($this->getJson('/api/messages', MISC_AUTH)->json('data.unread'))->toBe(0);
});

it('hides another user\'s message (404)', function () {
    apiUser();
    $other = User::factory()->create();
    $n = UserNotification::create(['user_id' => $other->id, 'title' => 'x', 'content' => 'x']);
    $this->postJson("/api/messages/{$n->id}/read", [], MISC_AUTH)->assertStatus(404);
});

// ---- 邀请返利 ----

it('returns invite info with masked downlines and per-downline rebate', function () {
    $u = apiUser(['ref_code' => 'REF12345']);
    $d1 = User::factory()->create(['ref_by' => $u->id, 'email' => 'alice@example.com']);
    Payback::create(['user_id' => $u->id, 'from_user_id' => $d1->id, 'amount' => 3.5]);
    Payback::create(['user_id' => $u->id, 'from_user_id' => $d1->id, 'amount' => 1.5]);

    $res = $this->getJson('/api/invite', MISC_AUTH)->assertOk()->assertJsonPath('ret', 1);
    expect($res->json('data.ref_code'))->toBe('REF12345');
    expect($res->json('data.invite_url'))->toContain('invite=REF12345');
    expect((float) $res->json('data.total_payback'))->toBe(5.0);
    expect($res->json('data.downline_count'))->toBe(1);
    expect($res->json('data.downlines.0.name'))->toBe('al***@example.com');   // 脱敏
    expect((float) $res->json('data.downlines.0.rebate'))->toBe(5.0);
});
