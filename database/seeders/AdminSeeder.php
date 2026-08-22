<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@test.local'],
            [
                'name' => 'admin',
                'password' => Hash::make('password'),
                'uuid' => (string) Str::uuid(),
                'passwd' => Str::random(6),
                'transfer_enable' => 0,
                'class' => 0,
                'class_expire' => now(),
                'invite_token' => Str::random(32),
                'api_token' => Str::random(60),
                'is_admin' => true,
            ]
        );
    }
}
