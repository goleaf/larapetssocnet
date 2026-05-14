<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scopeInThread returns only messages between two users', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();

    $threadMessage = Message::factory()->create([
        'sender_id' => $userA->getKey(),
        'receiver_id' => $userB->getKey(),
    ]);

    $otherMessage = Message::factory()->create([
        'sender_id' => $userA->getKey(),
        'receiver_id' => $userC->getKey(),
    ]);

    $messageIds = Message::query()
        ->inThread($userA->getKey(), $userB->getKey())
        ->pluck('messages.id');

    expect($messageIds)
        ->toContain($threadMessage->getKey())
        ->not->toContain($otherMessage->getKey());
});

it('scopeUnread returns only unread messages for a receiver', function (): void {
    $receiver = User::factory()->create();
    $sender = User::factory()->create();
    $otherUser = User::factory()->create();

    $unread = Message::factory()->create([
        'sender_id' => $sender->getKey(),
        'receiver_id' => $receiver->getKey(),
        'read_at' => null,
        'is_read' => false,
    ]);

    $read = Message::factory()->create([
        'sender_id' => $sender->getKey(),
        'receiver_id' => $receiver->getKey(),
        'read_at' => now(),
        'is_read' => true,
    ]);

    $otherReceiverUnread = Message::factory()->create([
        'sender_id' => $sender->getKey(),
        'receiver_id' => $otherUser->getKey(),
        'read_at' => null,
        'is_read' => false,
    ]);

    $messageIds = Message::query()
        ->unread($receiver->getKey())
        ->pluck('messages.id');

    expect($messageIds)
        ->toContain($unread->getKey())
        ->not->toContain($read->getKey())
        ->not->toContain($otherReceiverUnread->getKey());
});
