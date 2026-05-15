<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\SavedPost;
use App\Models\Identity\User;
use App\Services\SyncPostCountersService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('syncs post counters from relationships', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'likes_count' => 0,
        'comments_count' => 0,
        'save_count' => 0,
        'reactions_count' => 0,
    ]);

    Comment::factory()->count(2)->for($post)->for($author)->create();
    SavedPost::query()->create(['post_id' => $post->id, 'user_id' => $author->id]);

    $service = app(SyncPostCountersService::class);
    $service->sync($post);

    $post->refresh();

    expect($post->comments_count)->toBe(2)
        ->and($post->save_count)->toBe(1);
});
