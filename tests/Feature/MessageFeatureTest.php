<?php

namespace Tests\Feature;

use App\Models\Conversation;
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
            'sender_id' => $sender->getKey(),
            'body' => 'Hey there!',
        ]);

        $this->assertDatabaseHas('conversations', [
            'user_one_id' => min($sender->id, $receiver->id),
            'user_two_id' => max($sender->id, $receiver->id),
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

        $conversation = Conversation::query()->create([
            'user_one_id' => min($sender->id, $receiver->id),
            'user_two_id' => max($sender->id, $receiver->id),
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $sender->getKey(),
            'body' => 'Delete me',
        ]);

        $this->actingAs($sender)
            ->delete(route('messages.destroy', $message))
            ->assertRedirect();

        $this->assertSoftDeleted('messages', [
            'id' => $message->getKey(),
        ]);
    }
}
