<?php

use App\Models\Device;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function acctUser(string $username = 'alice'): User
{
    return User::factory()->create([
        'username' => $username,
        'password' => Hash::make('password123'),
        'api_token' => 'LEGACY_ACCT_TOKEN',
    ]);
}

function loginToken(object $t, string $username, ?string $deviceId): string
{
    $body = ['username' => $username, 'password' => 'password123'];
    if ($deviceId !== null) {
        $body['device_id'] = $deviceId;
    }

    return $t->postJson('/api/auth/login', $body)->assertOk()->json('data.token');
}

it('login with device_id returns a per-device token that authenticates', function () {
    acctUser();
    $token = loginToken($this, 'alice', 'devA');

    expect($token)->not->toBe('LEGACY_ACCT_TOKEN');
    expect(DeviceToken::where('token', $token)->where('device_id', 'devA')->exists())->toBeTrue();
    $this->getJson('/api/devices', ['Authorization' => "Bearer $token"])->assertOk()->assertJson(['ret' => 1]);
});

it('login without device_id falls back to the account token (legacy)', function () {
    acctUser();
    $token = loginToken($this, 'alice', null);

    expect($token)->toBe('LEGACY_ACCT_TOKEN');
    $this->getJson('/api/devices', ['Authorization' => 'Bearer LEGACY_ACCT_TOKEN'])->assertOk();
});

it('different devices get different tokens; same device reuses its token', function () {
    acctUser();
    $a1 = loginToken($this, 'alice', 'A');
    $b = loginToken($this, 'alice', 'B');
    $a2 = loginToken($this, 'alice', 'A');

    expect($a1)->not->toBe($b);
    expect($a1)->toBe($a2);            // 同设备复登不轮换
    expect(DeviceToken::count())->toBe(2);
});

it('removing a device revokes its token (force logout); other devices unaffected', function () {
    $u = acctUser();
    $tA = loginToken($this, 'alice', 'A');
    $tB = loginToken($this, 'alice', 'B');
    // 造出设备 A 的 devices 记录(下线删的是 devices 行,并连带吊销同 device_id 的 token)
    $dA = Device::create(['user_id' => $u->id, 'device_id' => 'A', 'platform' => 'windows', 'last_seen' => now()]);

    // 用 B 的 token 把 A 下线
    $this->deleteJson("/api/devices/{$dA->id}", [], ['Authorization' => "Bearer $tB"])
        ->assertOk()->assertJson(['ret' => 1]);

    // A 的 token 失效(强制登出),B 仍有效
    $this->getJson('/api/devices', ['Authorization' => "Bearer $tA"])->assertStatus(401);
    $this->getJson('/api/devices', ['Authorization' => "Bearer $tB"])->assertOk();
    expect(DeviceToken::where('device_id', 'A')->exists())->toBeFalse();
    expect(Device::where('device_id', 'A')->exists())->toBeFalse();
});

it('register with device_id returns a per-device token', function () {
    $token = $this->postJson('/api/auth/register', [
        'username' => 'bob123', 'password' => 'password123', 'device_id' => 'devZ',
    ])->assertOk()->json('data.token');

    expect(DeviceToken::where('token', $token)->where('device_id', 'devZ')->exists())->toBeTrue();
    $this->getJson('/api/devices', ['Authorization' => "Bearer $token"])->assertOk();
});
