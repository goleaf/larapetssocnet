<?php

use App\Models\Identity\User;
use App\Models\Messaging\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents a third user from reading others direct messages', function (): void {
    $firstUser = User::factory()->create(['is_private' => false]);
    $secondUser = User::factory()->create(['is_private' => false]);
    $intruder = User::factory()->create(['is_private' => false]);

    Message::factory()->create([
        'sender_id' => $firstUser->getKey(),
        'receiver_id' => $secondUser->getKey(),
        'body' => 'private-message-between-two-users',
    ]);

    $intruder->follow($secondUser);
    $secondUser->follow($intruder);

    $this->actingAs($intruder)
        ->get(route('messages.conversation', ['peer' => $secondUser]))
        ->assertOk()
        ->assertDontSee('private-message-between-two-users');
});

it('requires a mutual follow before opening a direct message thread', function (): void {
    $viewer = User::factory()->create(['is_private' => false]);
    $peer = User::factory()->create(['is_private' => false]);

    $viewer->follow($peer);

    $this->actingAs($viewer)
        ->get(route('messages.conversation', ['peer' => $peer]))
        ->assertForbidden();
});

it('prevents deleting message owned by another user', function (): void {
    $owner = User::factory()->create(['is_private' => false]);
    $peer = User::factory()->create(['is_private' => false]);
    $intruder = User::factory()->create(['is_private' => false]);

    $message = Message::factory()->create([
        'sender_id' => $owner->getKey(),
        'receiver_id' => $peer->getKey(),
    ]);

    $this->actingAs($intruder)
        ->delete(route('messages.destroy', ['message' => $message]))
        ->assertRedirect()
        ->assertSessionHasErrors('message');
});
