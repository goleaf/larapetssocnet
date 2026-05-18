<?php

use App\Models\Identity\User;
use App\Models\Messaging\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows one thread per partner with latest preview and unread badge', function (): void {
    $viewer = User::factory()->create(['is_private' => false]);
    $firstPeer = User::factory()->create(['is_private' => false, 'name' => 'First Peer']);
    $secondPeer = User::factory()->create(['is_private' => false, 'name' => 'Second Peer']);

    Message::factory()->create([
        'sender_id' => $firstPeer->getKey(),
        'receiver_id' => $viewer->getKey(),
        'body' => 'older first-peer message',
        'read_at' => now(),
        'is_read' => true,
    ]);

    Message::factory()->create([
        'sender_id' => $firstPeer->getKey(),
        'receiver_id' => $viewer->getKey(),
        'body' => 'newest first-peer message',
        'read_at' => null,
        'is_read' => false,
    ]);

    Message::factory()->create([
        'sender_id' => $viewer->getKey(),
        'receiver_id' => $secondPeer->getKey(),
        'body' => 'second-peer message',
        'read_at' => null,
        'is_read' => false,
    ]);

    $this->actingAs($viewer)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertSee('data-ui="messages-page"', false)
        ->assertSee('Simple inbox, newest conversations first.')
        ->assertSee('Search by name or username')
        ->assertSee('First Peer')
        ->assertSee('Second Peer')
        ->assertSee('newest first-peer message')
        ->assertSee('second-peer message')
        ->assertDontSee('messaging.messages');
});

it('loads a thread between two users with pagination', function (): void {
    $viewer = User::factory()->create(['is_private' => false]);
    $peer = User::factory()->create(['is_private' => false]);

    Message::factory()->count(35)->create([
        'sender_id' => $peer->getKey(),
        'receiver_id' => $viewer->getKey(),
    ]);

    $response = $this->actingAs($viewer)
        ->get(route('messages.conversation', ['peer' => $peer]));

    $response->assertOk();
    $response->assertSee('data-ui="messages-page"', false);
    $response->assertSee('Chat');
    $response->assertSee('Conversation with '.$peer->name);
    $response->assertSee('Inbox');
    $response->assertDontSee('messaging.messages');
    $response->assertSee($peer->name);
    expect($response->viewData('messages')->perPage())->toBe(30);
});
