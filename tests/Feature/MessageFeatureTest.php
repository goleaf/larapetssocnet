<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_message_and_view_conversation(): void
    {
        $sender = User::factory()->create(['is_private' => false]);
        $receiver = User::factory()->create(['is_private' => false]);

        $this->actingAs($sender)
            ->post(route('messages.store', $receiver), [
                'body' => 'Hey there!',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'sender_user_id' => $sender->getKey(),
            'recipient_user_id' => $receiver->getKey(),
            'body' => 'Hey there!',
        ]);

        $this->actingAs($sender)
            ->get(route('messages.conversation', $receiver))
            ->assertOk()
            ->assertSee('Hey there!');
    }

    public function test_user_can_soft_delete_own_message(): void
    {
        $sender = User::factory()->create(['is_private' => false]);
        $receiver = User::factory()->create(['is_private' => false]);

        $message = Message::query()->create([
            'sender_user_id' => $sender->getKey(),
            'recipient_user_id' => $receiver->getKey(),
            'body' => 'Delete me',
            'sent_at' => now(),
        ]);

        $this->actingAs($sender)
            ->delete(route('messages.destroy', $message))
            ->assertRedirect();

        $this->assertSoftDeleted('messages', [
            'id' => $message->getKey(),
        ]);
    }
}
