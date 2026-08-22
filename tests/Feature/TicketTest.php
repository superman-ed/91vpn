<?php

use App\Models\Ticket;
use App\Models\User;

it('user creates a ticket', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/user/ticket', [
        'subject' => '无法连接节点',
        'content' => '香港节点连不上',
    ])->assertRedirect();

    $ticket = Ticket::where('user_id', $user->id)->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->replies()->count())->toBe(1);   // 首条内容作为第一条回复
});

it('user replies to own ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 's', 'status' => 'open']);

    $this->actingAs($user)->post("/user/ticket/{$ticket->id}/reply", ['content' => '补充信息'])->assertRedirect();
    expect($ticket->replies()->where('content', '补充信息')->exists())->toBeTrue();
});

it('user cannot view others ticket', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $owner->id, 'subject' => 's', 'status' => 'open']);

    $this->actingAs($other)->get("/user/ticket/{$ticket->id}")->assertForbidden();
});

it('admin replies and closes ticket', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 's', 'status' => 'open']);

    $this->actingAs($admin)->post("/admin/tickets/{$ticket->id}/reply", ['content' => '已处理'])->assertRedirect();
    expect($ticket->replies()->where('is_admin', true)->exists())->toBeTrue();

    $this->actingAs($admin)->post("/admin/tickets/{$ticket->id}/close")->assertRedirect();
    expect($ticket->fresh()->status)->toBe('closed');
});
