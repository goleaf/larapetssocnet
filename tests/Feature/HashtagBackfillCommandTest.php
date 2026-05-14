<?php

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('backfills hashtags and recounts usage', function (): void {
    $author = User::factory()->create(['is_private' => false]);

    $post = Post::withoutEvents(function () use ($author): Post {
        return Post::factory()->for($author)->create([
            'body' => 'Backfill #cats post',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);
    });

    expect($post->hashtags()->count())->toBe(0);

    $this->artisan('hashtags:backfill-posts --recount')
        ->assertExitCode(0);

    $hashtag = Hashtag::query()->where('normalized_name', 'cats')->firstOrFail();

    $this->assertDatabaseHas('post_hashtag', [
        'post_id' => $post->getKey(),
        'hashtag_id' => $hashtag->getKey(),
    ]);

    $hashtag->refresh();
    expect($hashtag->posts_count)->toBe(1);
});
