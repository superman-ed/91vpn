<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    /**
     * `[!!]` 测试里 `is_admin => true` 而没写角色的，一律按超级管理员。
     *
     * 理由是保持既有测试的语义:在角色引入之前,"是管理员"就等于"什么都能做",
     * 48 个测试文件都建立在这个前提上。
     *
     * `[!!]` 这一条【只在工厂里】,不放模型事件 —— 放模型事件的话,
     * 生产环境新建一个管理员忘了选角色,就会静默变成超管。
     * 那是把一个"少了权限"的失误,变成一个"多了全部权限"的漏洞。
     */
    public function configure(): static
    {
        return $this->afterMaking(function (\App\Models\User $u) {
            if ($u->is_admin && $u->admin_role === null) {
                $u->admin_role = 'super';
            }
        });
    }

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // username 默认不设(测试按需显式传入);真实注册由 RegistrationService 写入
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'uuid' => (string) Str::uuid(),
            'passwd' => Str::lower(Str::random(6)),
            'u' => 0,
            'd' => 0,
            'transfer_enable' => 0,
            'transfer_today' => 0,
            'class' => 0,
            'class_expire' => now(),
            'ref_code' => Str::upper(Str::random(8)),
            'invite_token' => Str::random(32),
            'api_token' => Str::random(60),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
