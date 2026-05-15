<?php

use App\Actions\Posts\CreatePostAction;
use App\Enums\PostStatus;
use App\Events\PostCreated;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('creates a post and dispatches the post created event', function (): void {
    Event::fake([PostCreated::class]);

    $user = User::factory()->create();

    $post = app(CreatePostAction::class)->handle($user, [
        'body' => 'New post from action #action',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'media_files' => [],
    ]);

    expect($post->exists)->toBeTrue();
    expect($post->user_id)->toBe($user->getKey());
    expect($post->body)->toBe('New post from action #action');
    expect($post->tags()->where('slug', 'action')->exists())->toBeTrue();
    expect($post->status->value ?? $post->status)->toBe(PostStatus::Published->value);
    expect($post->published_at)->not->toBeNull();

    Event::assertDispatched(PostCreated::class);
});
