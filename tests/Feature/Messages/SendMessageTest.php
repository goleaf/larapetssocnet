<?php

use App\Enums\MessageStatus;
use App\Events\MessageSent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('sends a valid direct message', function (): void {
    Event::fake([MessageSent::class]);

    $sender = User::factory()->create(['is_private' => false]);
    $receiver = User::factory()->create(['is_private' => false]);

    $this->actingAs($sender)
        ->from(route('messages.conversation', ['peer' => $receiver]))
        ->post(route('messages.store', ['peer' => $receiver]), [
            'body' => 'hello there',
        ])
        ->assertRedirect(route('messages.conversation', ['peer' => $receiver]));

    $this->assertDatabaseHas('messages', [
        'sender_id' => $sender->getKey(),
        'receiver_id' => $receiver->getKey(),
        'body' => 'hello there',
        'status' => MessageStatus::Sent->value,
    ]);

    Event::assertDispatched(MessageSent::class);
});

it('rejects invalid message payload', function (): void {
    $sender = User::factory()->create(['is_private' => false]);
    $receiver = User::factory()->create(['is_private' => false]);

    $this->actingAs($sender)
        ->from(route('messages.conversation', ['peer' => $receiver]))
        ->post(route('messages.store', ['peer' => $receiver]), [
            'body' => '',
        ])
        ->assertRedirect(route('messages.conversation', ['peer' => $receiver]))
        ->assertSessionHasErrors('body');
});

it('blocks self messaging', function (): void {
    $sender = User::factory()->create(['is_private' => false]);

    $this->actingAs($sender)
        ->post(route('messages.store', ['peer' => $sender]), [
            'body' => 'self message',
        ])
        ->assertForbidden();
});
