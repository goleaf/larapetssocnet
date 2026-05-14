<?php

use App\Actions\SendMessageAction;
use App\Events\MessageSent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('creates a message and dispatches message sent event', function (): void {
    Event::fake([MessageSent::class]);

    $sender = User::factory()->create(['is_private' => false]);
    $receiver = User::factory()->create(['is_private' => false]);

    $message = app(SendMessageAction::class)->handle($sender, $receiver, [
        'body' => 'unit-test-message',
    ]);

    expect($message->exists)->toBeTrue();
    expect((int) $message->sender_id)->toBe((int) $sender->getKey());
    expect((int) $message->receiver_id)->toBe((int) $receiver->getKey());
    expect((string) $message->body)->toBe('unit-test-message');

    Event::assertDispatched(MessageSent::class);
});
