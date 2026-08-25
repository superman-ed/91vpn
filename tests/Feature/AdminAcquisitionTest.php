<?php

use App\Models\User;

function acqAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('classifies registration channels', function () {
    $inviter = User::factory()->create();
    User::factory()->create(['ref_by' => $inviter->id, 'reg_referer' => null]);   // 邀请
    User::factory()->create(['ref_by' => null, 'reg_referer' => null]);           // 直接
    User::factory()->create(['ref_by' => null, 'reg_referer' => 'https://t.me/somechannel/123']); // 外部来路

    $res = $this->actingAs(acqAdmin())->get('/admin/system/acquisition');
    $res->assertOk()->assertSee('t.me');

    $ch = $res->viewData('channels');
    expect($ch->get('邀请注册'))->toBe(1);
    // acqAdmin + inviter + 直接的那个 = 直接访问(都无 ref_by 无 referer)
    expect($ch->get('直接访问'))->toBe(3);
    expect($res->viewData('referers')->get('t.me'))->toBe(1);
});

it('records referer and ip on registration', function () {
    \Illuminate\Support\Facades\Cache::put('email_code:zoe@test.local', '123456', now()->addMinutes(5));
    $this->withSession(['captcha_answer' => 7])->post('/register', [
        'email' => 'zoe@test.local',
        'email_code' => '123456',
        'name' => 'Zoe',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'captcha' => '7',
    ], ['referer' => 'https://blog.example.com/vpn']);

    $u = User::where('email', 'zoe@test.local')->first();
    expect($u)->not->toBeNull();
    expect($u->reg_referer)->toBe('https://blog.example.com/vpn');
    expect($u->reg_ip)->not->toBeNull();
});
