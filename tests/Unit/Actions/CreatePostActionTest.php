<?php

use App\Actions\Posts\CreatePostAction;
use App\Enums\PostStatus;
use App\Events\PostCreated;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Support\Posts\PostCreationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('creates a post and dispatches the post created event', function (): void {
    Event::fake([PostCreated::class]);

    $user = User::factory()->create();

    $result = app(CreatePostAction::class)->handle($user, PostCreationInput::fromUserInput($user, [
        'body' => 'New post from action #action',
        'visibility' => Post::VISIBILITY_PUBLIC,
        'media_files' => [],
    ]));
    $post = $result->createdPost();

    expect($result->duplicateDetected)->toBeFalse();
    expect($post->exists)->toBeTrue()
        ->and($post->user_id)->toBe($user->getKey())
        ->and($post->body)->toBe('New post from action #action')
        ->and($post->content_hash)->toBe(hash('sha256', 'new post from action #action'))
        ->and($post->tags()->where('slug', 'action')->exists())->toBeTrue()
        ->and($post->status->value ?? $post->status)->toBe(PostStatus::Published->value)
        ->and($post->published_at)->not->toBeNull();

    Event::assertDispatched(PostCreated::class);
});
