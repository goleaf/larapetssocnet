<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_inbox_threads(): void
    {
        $viewer = User::factory()->create(['is_private' => false]);
        $peer = User::factory()->create([
            'is_private' => false,
            'name' => 'Coco Owner',
            'username' => 'cocoowner',
        ]);

        $conversation = Conversation::query()->create([
            'user_one_id' => min($viewer->id, $peer->id),
            'user_two_id' => max($viewer->id, $peer->id),
            'last_message_at' => now(),
            'last_message_preview' => 'hello from coco',
            'user_one_unread_count' => 1,
            'user_two_unread_count' => 0,
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $peer->getKey(),
            'body' => 'hello from coco',
        ]);

        $this->actingAs($viewer)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Messages')
            ->assertSee('Coco Owner')
            ->assertSee('@cocoowner')
            ->assertSee('hello from coco');
    }

    public function test_user_can_filter_inbox_threads_by_search_query(): void
    {
        $viewer = User::factory()->create(['is_private' => false]);
        $catPeer = User::factory()->create([
            'is_private' => false,
            'name' => 'Cat Friend',
            'username' => 'catfriend',
        ]);
        $dogPeer = User::factory()->create([
            'is_private' => false,
            'name' => 'Dog Buddy',
            'username' => 'dogbuddy',
        ]);

        $catConversation = Conversation::query()->create([
            'user_one_id' => min($viewer->id, $catPeer->id),
            'user_two_id' => max($viewer->id, $catPeer->id),
            'last_message_at' => now(),
            'last_message_preview' => 'cat thread',
        ]);

        $dogConversation = Conversation::query()->create([
            'user_one_id' => min($viewer->id, $dogPeer->id),
            'user_two_id' => max($viewer->id, $dogPeer->id),
            'last_message_at' => now(),
            'last_message_preview' => 'dog thread',
        ]);

        Message::query()->create([
            'conversation_id' => $catConversation->getKey(),
            'sender_id' => $catPeer->getKey(),
            'body' => 'cat thread',
        ]);

        Message::query()->create([
            'conversation_id' => $dogConversation->getKey(),
            'sender_id' => $dogPeer->getKey(),
            'body' => 'dog thread',
        ]);

        $this->actingAs($viewer)
            ->get(route('messages.index', ['q' => 'cat']))
            ->assertOk()
            ->assertSee('Cat Friend')
            ->assertDontSee('dog thread');
    }

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
