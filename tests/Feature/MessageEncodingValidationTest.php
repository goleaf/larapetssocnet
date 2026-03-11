<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('accepts valid utf-8 message bodies', function (): void {
    $sender = User::factory()->create(['is_private' => false]);
    $receiver = User::factory()->create(['is_private' => false]);

    $this->actingAs($sender)
        ->post(route('messages.store', $receiver), [
            'body' => 'Sveikas 👋',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', [
        'sender_id' => $sender->getKey(),
        'body' => 'Sveikas 👋',
    ]);
});

it('rejects message bodies that are not valid utf-8', function (): void {
    $sender = User::factory()->create(['is_private' => false]);
    $receiver = User::factory()->create(['is_private' => false]);

    $invalidBody = "\xB1\x31";

    $this->actingAs($sender)
        ->post(route('messages.store', $receiver), [
            'body' => $invalidBody,
        ])
        ->assertSessionHasErrors('body');

    $this->assertDatabaseMissing('messages', [
        'sender_id' => $sender->getKey(),
    ]);
});
