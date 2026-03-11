<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('filters messages in a thread between two users', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $thirdUser = User::factory()->create();

    $threadConversation = Conversation::factory()->create([
        'user_one_id' => $user->getKey(),
        'user_two_id' => $otherUser->getKey(),
    ]);

    $otherConversation = Conversation::factory()->create([
        'user_one_id' => $user->getKey(),
        'user_two_id' => $thirdUser->getKey(),
    ]);

    $threadMessage = Message::factory()->create([
        'conversation_id' => $threadConversation->getKey(),
        'sender_id' => $otherUser->getKey(),
    ]);

    $otherMessage = Message::factory()->create([
        'conversation_id' => $otherConversation->getKey(),
        'sender_id' => $thirdUser->getKey(),
    ]);

    $messageIds = Message::query()
        ->inThread($user->getKey(), $otherUser->getKey())
        ->pluck('messages.id');

    expect($messageIds)
        ->toContain($threadMessage->getKey())
        ->not->toContain($otherMessage->getKey());
});

it('filters unread messages for a user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $thirdUser = User::factory()->create();

    $conversation = Conversation::factory()->create([
        'user_one_id' => $user->getKey(),
        'user_two_id' => $otherUser->getKey(),
    ]);

    $otherConversation = Conversation::factory()->create([
        'user_one_id' => $thirdUser->getKey(),
        'user_two_id' => $otherUser->getKey(),
    ]);

    $unreadForUser = Message::factory()->create([
        'conversation_id' => $conversation->getKey(),
        'sender_id' => $otherUser->getKey(),
        'is_read' => false,
        'read_at' => null,
    ]);

    $readMessage = Message::factory()->create([
        'conversation_id' => $conversation->getKey(),
        'sender_id' => $otherUser->getKey(),
        'is_read' => true,
        'read_at' => now(),
    ]);

    $ownMessage = Message::factory()->create([
        'conversation_id' => $conversation->getKey(),
        'sender_id' => $user->getKey(),
        'is_read' => false,
        'read_at' => null,
    ]);

    $messageFromAnotherConversation = Message::factory()->create([
        'conversation_id' => $otherConversation->getKey(),
        'sender_id' => $otherUser->getKey(),
        'is_read' => false,
        'read_at' => null,
    ]);

    $messageIds = Message::query()
        ->unread($user->getKey())
        ->pluck('messages.id');

    expect($messageIds)
        ->toContain($unreadForUser->getKey())
        ->not->toContain($readMessage->getKey())
        ->not->toContain($ownMessage->getKey())
        ->not->toContain($messageFromAnotherConversation->getKey());
});
