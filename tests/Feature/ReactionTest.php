<?php

use App\Models\Post;
use App\Models\User;
use App\Notifications\NewReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('toggles post reactions and updates likes_count', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => 'public',
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'love'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.current_reaction', 'love');

    $this->assertDatabaseHas('reactions', [
        'reactable_type' => Post::class,
        'reactable_id' => $post->id,
        'user_id' => $user->id,
        'type' => 'love',
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'love'])
        ->assertOk()
        ->assertJsonPath('data.likes_count', 0)
        ->assertJsonPath('data.current_reaction', null);
});

it('accepts all supported reaction types and rejects invalid type', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => 'public',
    ]);

    foreach (['love', 'cute', 'funny', 'wow', 'sad', 'support'] as $type) {
        $this->actingAs($user)
            ->postJson(route('posts.react', $post), ['type' => $type])
            ->assertOk()
            ->assertJsonPath('data.current_reaction', $type);
    }

    $this->actingAs($user)
        ->postJson(route('posts.react', $post), ['type' => 'angry'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

it('sends reaction notification with relation-light models', function (): void {
    Notification::fake();

    $actor = User::factory()->create();
    $author = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => 'public',
    ]);

    Post::factory()->create([
        'user_id' => $actor->id,
        'visibility' => 'public',
    ]);

    $post->load('author');
    $actor->load('media', 'posts');

    $this->actingAs($actor)
        ->postJson(route('posts.react', $post), ['type' => 'love'])
        ->assertOk();

    expect($actor->relationLoaded('media'))->toBeTrue();
    expect($actor->relationLoaded('posts'))->toBeTrue();
    expect($post->relationLoaded('author'))->toBeTrue();

    Notification::assertSentTo($author, NewReaction::class, function (NewReaction $notification): bool {
        return ! $notification->post->relationLoaded('author')
            && ! $notification->reactor->relationLoaded('media')
            && $notification->reactor->relationLoaded('posts');
    });
});
