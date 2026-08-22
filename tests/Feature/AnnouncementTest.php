<?php

use App\Models\Announcement;
use App\Models\User;

it('shows published announcements only', function () {
    Announcement::create(['title' => '欢迎使用', 'content' => '这是公告内容', 'published' => true]);
    Announcement::create(['title' => '草稿公告', 'content' => '隐藏', 'published' => false]);

    $this->actingAs(User::factory()->create())->get('/user/announcement')
        ->assertOk()
        ->assertSee('欢迎使用')
        ->assertDontSee('草稿公告');
});
