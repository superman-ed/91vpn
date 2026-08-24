<?php

use App\Models\Announcement;
use App\Models\Plan;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['is_admin' => true]));

it('batch-creates same-named plans for each priced duration', function () {
    $this->actingAs($this->admin)->post('/admin/plans', [
        'name' => 'VIP①', 'transfer_gb' => 100, 'class' => 1, 'speed_limit' => 100, 'ip_limit' => 4, 'on_sale' => 1,
        'prices' => ['month' => 30, 'quarter' => 85, 'half_year' => '', 'year' => 300],
    ])->assertRedirect('/admin/plans');

    // 只为填了价格的 3 档各建一行(半年留空跳过)
    expect(Plan::where('name', 'VIP①')->count())->toBe(3);
    expect(Plan::where('name', 'VIP①')->where('period', 'year')->first()->duration_days)->toBe(365);
});

it('rejects a plan group with no prices filled', function () {
    $this->actingAs($this->admin)->post('/admin/plans', [
        'name' => 'VIP①', 'transfer_gb' => 100, 'class' => 1, 'prices' => ['month' => '', 'quarter' => '', 'half_year' => '', 'year' => ''],
    ])->assertSessionHasErrors('prices');
    expect(Plan::count())->toBe(0);
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
