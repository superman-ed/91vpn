<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('user management includes admins (admin is also a user)', function () {
    User::factory()->create(['email' => 'customer@test.local', 'is_admin' => false]);
    User::factory()->create(['email' => 'admin2@test.local', 'is_admin' => true]);

    $this->actingAs($this->admin)->get('/admin/users')->assertOk()
        ->assertSee('customer@test.local')
        ->assertSee('admin2@test.local');   // 管理员也在用户管理里
});

it('refuses to ban an admin from user management', function () {
    $other = User::factory()->create(['is_admin' => true, 'banned' => false]);

    $this->actingAs($this->admin)->post("/admin/users/{$other->id}/toggle-ban");
    expect($other->fresh()->banned)->toBeFalse();
});

it('admin page lists only admins', function () {
    User::factory()->create(['email' => 'customer@test.local', 'is_admin' => false]);

    $this->actingAs($this->admin)->get('/admin/admins')->assertOk()
        ->assertSee($this->admin->email)
        ->assertDontSee('customer@test.local');
});

// `[!!]` 管理员按 **username** 标识，不是 email。
//
// 这三个用例原本 POST 的是 email —— 那是接口早先的形态。控制器改成
// 按 username 之后（username 是 required），每次提交都因缺字段被打回，
// 三个用例一直红着。断言还在，被断言的东西变了。
//
// 当时最误导的是第三个：它断言"无密码时不该建出账号"，失败信息看起来像
// "无密码也建成了"，实际上根本没走到创建那一步 —— 那个 email 是同文件
// 前面的用例留下的。**假红比不测更糟**，它让人怀疑一个好的功能。
it('promotes an existing user to admin', function () {
    $u = User::factory()->create(['username' => 'promoteme', 'is_admin' => false]);

    $this->actingAs($this->admin)->post('/admin/admins',
        ['username' => 'promoteme', 'admin_role' => 'support'])
        ->assertRedirect('/admin/admins');
    // 提升时指定的角色要真的落下去 —— 只断言 is_admin 的话，
    // 角色没存也照样绿，而那个人进来之后什么都做不了。
    expect($u->fresh()->is_admin)->toBeTrue()
        ->and($u->fresh()->admin_role)->toBe('support');
});

it('creates a new admin account with password', function () {
    $this->actingAs($this->admin)->post('/admin/admins', [
        'username' => 'newadmin', 'name' => 'Boss', 'password' => 'secret123',
        'admin_role' => 'ops',
    ])->assertRedirect('/admin/admins');

    $created = User::where('username', 'newadmin')->first();
    expect($created)->not->toBeNull();
    expect($created->is_admin)->toBeTrue();
    // 新建的管理员必须能用给定密码登录 —— 只断言 is_admin 的话，
    // 密码没被正确 hash 也照样绿。
    expect(Hash::check('secret123', $created->password))->toBeTrue();
});

it('rejects new admin account without password', function () {
    $this->actingAs($this->admin)->post('/admin/admins',
        ['username' => 'nopassuser', 'admin_role' => 'ops'])
        ->assertSessionHasErrors('password');
    expect(User::where('username', 'nopassuser')->exists())->toBeFalse();
});

// `[!]` 用户名的格式约束也要锁住：控制器用正则限定 4~20 位字母数字下划线。
// 不测的话，哪天有人放宽成任意字符串，注入面就悄悄变大了。
it('rejects an invalid username', function () {
    foreach (['ab', 'has space', 'with-dash', str_repeat('x', 21)] as $bad) {
        $this->actingAs($this->admin)->post('/admin/admins',
            ['username' => $bad, 'password' => 'secret123', 'admin_role' => 'ops'])
            ->assertSessionHasErrors('username');
    }
    expect(User::where('is_admin', true)->count())->toBe(1); // 只有 beforeEach 那个
});

it('demotes another admin but not self or the last admin', function () {
    $other = User::factory()->create(['is_admin' => true]);

    // 撤销其他管理员 OK
    $this->actingAs($this->admin)->delete("/admin/admins/{$other->id}")->assertRedirect();
    expect($other->fresh()->is_admin)->toBeFalse();

    // 不能撤销自己
    $this->actingAs($this->admin)->delete("/admin/admins/{$this->admin->id}");
    expect($this->admin->fresh()->is_admin)->toBeTrue();
});
