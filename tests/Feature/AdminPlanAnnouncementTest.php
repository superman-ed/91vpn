<?php

use App\Models\Announcement;
use App\Models\Plan;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('creates a plan', function () {
    $this->actingAs($this->admin)->post('/admin/plans', [
        'name' => 'VIP①', 'price' => 30, 'period' => 'month', 'transfer_gb' => 100,
        'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'duration_days' => 30, 'on_sale' => 1,
    ])->assertRedirect('/admin/plans');
    expect(Plan::where('name', 'VIP①')->exists())->toBeTrue();
});

it('updates and deletes a plan', function () {
    $plan = Plan::create(['name' => 'x', 'price' => 10, 'period' => 'month', 'transfer_gb' => 10, 'class' => 1, 'speed_limit' => 0, 'ip_limit' => 1, 'duration_days' => 30]);
    $this->actingAs($this->admin)->put("/admin/plans/{$plan->id}", [
        'name' => 'y', 'price' => 20, 'period' => 'month', 'transfer_gb' => 20, 'class' => 2, 'speed_limit' => 0, 'ip_limit' => 2, 'duration_days' => 30,
    ])->assertRedirect('/admin/plans');
    expect($plan->fresh()->name)->toBe('y');
    $this->actingAs($this->admin)->delete("/admin/plans/{$plan->id}")->assertRedirect('/admin/plans');
    expect(Plan::find($plan->id))->toBeNull();
});

it('creates and deletes announcement', function () {
    $this->actingAs($this->admin)->post('/admin/announcements', ['title' => '维护通知', 'content' => '内容', 'published' => 1])->assertRedirect('/admin/announcements');
    $a = Announcement::where('title', '维护通知')->first();
    expect($a)->not->toBeNull();
    $this->actingAs($this->admin)->delete("/admin/announcements/{$a->id}")->assertRedirect('/admin/announcements');
    expect(Announcement::find($a->id))->toBeNull();
});
