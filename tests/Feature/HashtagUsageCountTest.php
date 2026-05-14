<?php

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use App\Services\HashtagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates hashtag usage count on delete and restore', function (): void {
    $author = User::factory()->create(['is_private' => false]);
    $post = Post::factory()->for($author)->create([
        'body' => 'Hello #cats',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    app(HashtagService::class)->syncHashtags($post);

    $hashtag = Hashtag::query()->where('normalized_name', 'cats')->firstOrFail();
    expect($hashtag->posts_count)->toBe(1);

    $post->delete();
    $hashtag->refresh();
    expect($hashtag->posts_count)->toBe(0);

    $post->restore();
    $hashtag->refresh();
    expect($hashtag->posts_count)->toBe(1);
});
