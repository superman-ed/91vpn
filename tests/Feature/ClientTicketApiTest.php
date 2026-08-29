<?php

use App\Models\Ticket;
use App\Models\User;

const TK_AUTH = ['Authorization' => 'Bearer TESTTOKEN123'];

it('creates a ticket with the first message', function () {
    apiUser();
    $res = $this->postJson('/api/tickets', ['subject' => '无法连接', 'content' => '香港节点连不上'], TK_AUTH)
        ->assertOk()->assertJsonPath('ret', 1);
    expect($res->json('data.subject'))->toBe('无法连接');
    expect($res->json('data.status'))->toBe('open');
    expect($res->json('data.replies'))->toHaveCount(1);
    expect($res->json('data.replies.0.is_admin'))->toBeFalse();
    expect($res->json('data.replies.0.content'))->toBe('香港节点连不上');
});

it('lists my tickets with reply counts', function () {
    apiUser();
    $this->postJson('/api/tickets', ['subject' => 'A', 'content' => 'a'], TK_AUTH);
    $this->postJson('/api/tickets', ['subject' => 'B', 'content' => 'b'], TK_AUTH);
    $res = $this->getJson('/api/tickets', TK_AUTH)->assertOk();
    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('data.0.replies_count'))->toBe(1);
});

it('shows a ticket with its thread', function () {
    apiUser();
    $id = $this->postJson('/api/tickets', ['subject' => 'X', 'content' => 'x'], TK_AUTH)->json('data.id');
    $this->getJson("/api/tickets/{$id}", TK_AUTH)->assertOk()->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.replies.0.content', 'x');
});

it('appends a reply and reopens the ticket', function () {
    apiUser();
    $id = $this->postJson('/api/tickets', ['subject' => 'X', 'content' => 'x'], TK_AUTH)->json('data.id');
    Ticket::find($id)->update(['status' => 'closed']);
    $res = $this->postJson("/api/tickets/{$id}/reply", ['content' => '还是不行'], TK_AUTH)->assertOk();
    expect($res->json('data.status'))->toBe('open');
    expect($res->json('data.replies'))->toHaveCount(2);
});

it('closes a ticket', function () {
    apiUser();
    $id = $this->postJson('/api/tickets', ['subject' => 'X', 'content' => 'x'], TK_AUTH)->json('data.id');
    $this->postJson("/api/tickets/{$id}/close", [], TK_AUTH)->assertOk()->assertJsonPath('ret', 1);
    expect(Ticket::find($id)->status)->toBe('closed');
});

it('hides another user\'s ticket (404)', function () {
    apiUser();
    $other = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $other->id, 'subject' => '别人的', 'status' => 'open', 'last_reply_at' => now()]);
    $this->getJson("/api/tickets/{$ticket->id}", TK_AUTH)->assertStatus(404);
    $this->postJson("/api/tickets/{$ticket->id}/reply", ['content' => 'x'], TK_AUTH)->assertStatus(404);
    $this->postJson("/api/tickets/{$ticket->id}/close", [], TK_AUTH)->assertStatus(404);
});
